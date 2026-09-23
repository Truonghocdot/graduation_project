<?php

namespace App\Services\Matching;

use App\Enums\AssignmentStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverOfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\Assignment;
use App\Models\DriverOffer;
use App\Models\DriverProfile;
use App\Models\OutboxEvent;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use App\Models\User;
use App\Services\Booking\IdempotencyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OfferResponseService
{
    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function respond(
        User $user,
        DriverOffer $driverOffer,
        string $action,
        string $idempotencyKey,
    ): DriverOffer {
        return DB::transaction(function () use ($user, $driverOffer, $action, $idempotencyKey): DriverOffer {
            $idempotency = $this->idempotency->begin(
                $user,
                'driver-offer.respond',
                $idempotencyKey,
                ['offer_id' => $driverOffer->public_id, 'action' => $action],
            );

            if ($idempotency->status === 'COMPLETED') {
                return DriverOffer::query()->findOrFail($idempotency->resource_id)->load([
                    'serviceRequest.payment',
                    'driverProfile.user',
                    'assignment',
                ]);
            }

            $offer = DriverOffer::query()
                ->with('driverProfile')
                ->lockForUpdate()
                ->findOrFail($driverOffer->id);

            if ($offer->driverProfile->user_id !== $user->id) {
                abort(404);
            }

            if ($action === 'decline') {
                $result = $this->decline($offer);
            } else {
                $result = $this->accept($offer);
            }

            $this->idempotency->complete(
                $idempotency,
                200,
                ['id' => $result->public_id],
                DriverOffer::class,
                $result->id,
            );

            return $result;
        });
    }

    private function decline(DriverOffer $offer): DriverOffer
    {
        if ($offer->status === DriverOfferStatus::Declined) {
            return $offer->load(['serviceRequest.payment', 'driverProfile.user']);
        }

        if ($offer->status !== DriverOfferStatus::Pending) {
            throw ValidationException::withMessages([
                'offer' => ['Đề nghị không còn ở trạng thái chờ phản hồi.'],
            ]);
        }

        if ($offer->expires_at->isPast()) {
            $offer->forceFill([
                'status' => DriverOfferStatus::Expired,
                'responded_at' => now(),
            ])->save();
            $offer->driverProfile()->update([
                'availability_status' => DriverAvailabilityStatus::Online,
            ]);

            return $offer->load(['serviceRequest.payment', 'driverProfile.user']);
        }

        $offer->forceFill([
            'status' => DriverOfferStatus::Declined,
            'responded_at' => now(),
        ])->save();
        $offer->driverProfile()->increment('ignored_offer_count');
        $offer->driverProfile()->update([
            'availability_status' => DriverAvailabilityStatus::Online,
        ]);

        return $offer->load(['serviceRequest.payment', 'driverProfile.user']);
    }

    private function accept(DriverOffer $offer): DriverOffer
    {
        $request = ServiceRequest::query()->lockForUpdate()->findOrFail($offer->service_request_id);

        if ($offer->status === DriverOfferStatus::Accepted) {
            return $offer->load(['serviceRequest.payment', 'driverProfile.user', 'assignment']);
        }

        if ($offer->status !== DriverOfferStatus::Pending) {
            throw ValidationException::withMessages([
                'offer' => ['Đề nghị không còn ở trạng thái chờ phản hồi.'],
            ]);
        }

        if ($offer->expires_at->isPast()) {
            $offer->forceFill([
                'status' => DriverOfferStatus::Expired,
                'responded_at' => now(),
            ])->save();
            $offer->driverProfile()->update([
                'availability_status' => DriverAvailabilityStatus::Online,
            ]);

            return $offer->load(['serviceRequest.payment', 'driverProfile.user']);
        }

        if ($request->status !== ServiceRequestStatus::SearchingDriver) {
            throw ValidationException::withMessages([
                'offer' => ['Yêu cầu đã có tài xế nhận hoặc không còn có thể tìm kiếm.'],
            ]);
        }

        if (! in_array($offer->driverProfile->availability_status, [
            DriverAvailabilityStatus::Online,
            DriverAvailabilityStatus::Offered,
        ], true)) {
            throw ValidationException::withMessages([
                'offer' => ['Tài xế không còn sẵn sàng cho đề nghị này.'],
            ]);
        }

        $vehicle = $offer->driverProfile->vehicles()
            ->where('is_selected', true)
            ->where('status', 'APPROVED')
            ->firstOrFail();
        $assignment = Assignment::query()->create([
            'service_request_id' => $request->id,
            'driver_profile_id' => $offer->driver_profile_id,
            'vehicle_id' => $vehicle->id,
            'accepted_offer_id' => $offer->id,
            'status' => AssignmentStatus::Active,
            'assigned_at' => now(),
        ]);

        $offer->forceFill([
            'status' => DriverOfferStatus::Accepted,
            'responded_at' => now(),
        ])->save();
        DriverOffer::query()
            ->where('service_request_id', $request->id)
            ->where('id', '<>', $offer->id)
            ->where('status', DriverOfferStatus::Pending->value)
            ->update([
                'status' => DriverOfferStatus::Cancelled->value,
                'responded_at' => now(),
            ]);
        $cancelledProfileIds = DriverOffer::query()
            ->where('service_request_id', $request->id)
            ->where('status', DriverOfferStatus::Cancelled->value)
            ->where('driver_profile_id', '<>', $offer->driver_profile_id)
            ->pluck('driver_profile_id');
        DriverProfile::query()->whereIn('id', $cancelledProfileIds)->update([
            'availability_status' => DriverAvailabilityStatus::Online,
        ]);
        $offer->driverProfile()->update([
            'availability_status' => DriverAvailabilityStatus::Busy,
            'offline_at' => null,
        ]);
        $offer->driverProfile()->increment('accepted_offer_count');
        $offer->driverProfile->refresh();
        $offer->driverProfile->forceFill([
            'acceptance_rate' => $offer->driverProfile->offer_count === 0
                ? 0
                : $offer->driverProfile->accepted_offer_count / $offer->driverProfile->offer_count,
        ])->save();
        $fromStatus = $request->status;
        $nextStatus = $request->service_type === ServiceType::Delivery
            ? ServiceRequestStatus::DriverArrivingPickup
            : ServiceRequestStatus::DriverArriving;
        $request->forceFill([
            'status' => $nextStatus,
            'version' => $request->version + 1,
        ])->save();
        ServiceStatusHistory::query()->create([
            'service_request_id' => $request->id,
            'version' => $request->version,
            'from_status' => $fromStatus->value,
            'to_status' => $nextStatus->value,
            'actor_user_id' => $offer->driverProfile->user_id,
            'actor_type' => 'DRIVER',
            'metadata' => [
                'assignment_id' => $assignment->public_id,
                'offer_id' => $offer->public_id,
            ],
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
        OutboxEvent::query()->create([
            'event_type' => 'DRIVER_ASSIGNED',
            'aggregate_type' => 'SERVICE_REQUEST',
            'aggregate_id' => $request->id,
            'aggregate_version' => $request->version,
            'payload' => [
                'service_request_id' => $request->public_id,
                'assignment_id' => $assignment->public_id,
                'driver_profile_id' => $offer->driverProfile->public_id,
                'vehicle_id' => $vehicle->public_id,
            ],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);

        return $offer->load(['serviceRequest.payment', 'driverProfile.user', 'assignment']);
    }
}
