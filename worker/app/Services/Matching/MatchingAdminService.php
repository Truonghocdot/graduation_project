<?php

namespace App\Services\Matching;

use App\Enums\AssignmentStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverOfferStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceRequestStatus;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\DriverOffer;
use App\Models\DriverProfile;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use App\Models\User;
use App\Services\Booking\VoucherRedemptionService;
use App\Services\Booking\WalletPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MatchingAdminService
{
    public function __construct(
        private readonly WalletPaymentService $walletPayment,
        private readonly VoucherRedemptionService $voucherRedemption,
    ) {}

    public function restart(ServiceRequest $serviceRequest, User $admin, string $reasonCode): ServiceRequest
    {
        return DB::transaction(function () use ($serviceRequest, $admin, $reasonCode): ServiceRequest {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if (! in_array($request->status, [
                ServiceRequestStatus::SearchingDriver,
                ServiceRequestStatus::Assigned,
                ServiceRequestStatus::DriverArriving,
                ServiceRequestStatus::DriverArrivingPickup,
            ], true)) {
                $this->invalidState();
            }

            $before = $request->attributesToArray();
            $this->closeAssignment($request, $reasonCode);
            $offeredProfileIds = DriverOffer::query()
                ->where('service_request_id', $request->id)
                ->where('status', DriverOfferStatus::Pending->value)
                ->pluck('driver_profile_id');
            DriverOffer::query()
                ->where('service_request_id', $request->id)
                ->where('status', DriverOfferStatus::Pending->value)
                ->update([
                    'status' => DriverOfferStatus::Cancelled->value,
                    'responded_at' => now(),
                ]);
            DriverProfile::query()
                ->whereIn('id', $offeredProfileIds)
                ->where('availability_status', DriverAvailabilityStatus::Offered->value)
                ->update(['availability_status' => DriverAvailabilityStatus::Online->value]);
            $fromStatus = $request->status;
            $request->forceFill([
                'status' => ServiceRequestStatus::SearchingDriver,
                'search_started_at' => now(),
                'search_attempt' => 0,
                'version' => $request->version + 1,
            ])->save();
            $this->recordStatus($request, $fromStatus, $admin, $reasonCode);
            $this->outbox($request, 'MATCHING_RESTARTED');
            $this->audit($request, $admin, 'MATCHING_RESTARTED', $before, $reasonCode);

            return $request->load(['assignments.driverProfile.user', 'driverOffers']);
        });
    }

    public function cancel(ServiceRequest $serviceRequest, User $admin, string $reasonCode): ServiceRequest
    {
        return DB::transaction(function () use ($serviceRequest, $admin, $reasonCode): ServiceRequest {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if (! in_array($request->status, [
                ServiceRequestStatus::Scheduled,
                ServiceRequestStatus::SearchingDriver,
                ServiceRequestStatus::Assigned,
                ServiceRequestStatus::DriverArriving,
                ServiceRequestStatus::DriverArrivingPickup,
                ServiceRequestStatus::DriverArrived,
                ServiceRequestStatus::AtPickup,
            ], true)) {
                $this->invalidState();
            }

            $before = $request->attributesToArray();
            $this->closeAssignment($request, $reasonCode);
            $offeredProfileIds = DriverOffer::query()
                ->where('service_request_id', $request->id)
                ->where('status', DriverOfferStatus::Pending->value)
                ->pluck('driver_profile_id');
            DriverOffer::query()
                ->where('service_request_id', $request->id)
                ->where('status', DriverOfferStatus::Pending->value)
                ->update([
                    'status' => DriverOfferStatus::Cancelled->value,
                    'responded_at' => now(),
                ]);
            DriverProfile::query()
                ->whereIn('id', $offeredProfileIds)
                ->where('availability_status', DriverAvailabilityStatus::Offered->value)
                ->update(['availability_status' => DriverAvailabilityStatus::Online->value]);
            $payment = Payment::query()->where('service_request_id', $request->id)->lockForUpdate()->first();

            if ($payment !== null) {
                if ($payment->method === PaymentMethod::Wallet) {
                    $this->walletPayment->refund(
                        $payment,
                        (int) $payment->payer_user_id,
                        $payment->customer_payable,
                        'admin:'.$admin->id.':'.$reasonCode,
                    );
                }
                $this->voucherRedemption->restore($payment, $reasonCode);
                $payment->forceFill([
                    'status' => PaymentStatus::Cancelled,
                    'version' => $payment->version + 1,
                ])->save();
            }

            $fromStatus = $request->status;
            $request->forceFill([
                'status' => ServiceRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $admin->id,
                'cancellation_reason_code' => $reasonCode,
                'version' => $request->version + 1,
            ])->save();
            $this->recordStatus($request, $fromStatus, $admin, $reasonCode);
            $this->outbox($request, 'SERVICE_REQUEST_CANCELLED');
            $this->audit($request, $admin, 'SERVICE_REQUEST_CANCELLED', $before, $reasonCode);

            return $request->load(['assignments.driverProfile.user', 'driverOffers', 'payment']);
        });
    }

    private function closeAssignment(ServiceRequest $request, string $reasonCode): void
    {
        $assignment = Assignment::query()
            ->where('service_request_id', $request->id)
            ->where('status', AssignmentStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if ($assignment === null) {
            return;
        }

        $assignment->forceFill([
            'status' => AssignmentStatus::Cancelled,
            'closed_at' => now(),
            'close_reason_code' => $reasonCode,
        ])->save();
        $assignment->driverProfile()->update([
            'availability_status' => DriverAvailabilityStatus::Online,
        ]);
    }

    private function recordStatus(
        ServiceRequest $request,
        ServiceRequestStatus $fromStatus,
        User $admin,
        string $reasonCode,
    ): void {
        ServiceStatusHistory::query()->create([
            'service_request_id' => $request->id,
            'version' => $request->version,
            'from_status' => $fromStatus->value,
            'to_status' => $request->status->value,
            'actor_user_id' => $admin->id,
            'actor_type' => 'ADMIN',
            'reason_code' => $reasonCode,
            'metadata' => [],
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function outbox(ServiceRequest $request, string $eventType): void
    {
        OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => 'SERVICE_REQUEST',
            'aggregate_id' => $request->id,
            'aggregate_version' => $request->version,
            'payload' => [
                'service_request_id' => $request->public_id,
                'status' => $request->status->value,
            ],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $before */
    private function audit(
        ServiceRequest $request,
        User $admin,
        string $action,
        array $before,
        string $reasonCode,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_role' => RoleKey::Admin->value,
            'action' => $action,
            'subject_type' => ServiceRequest::class,
            'subject_id' => $request->id,
            'before' => $before,
            'after' => $request->attributesToArray(),
            'reason_code' => $reasonCode,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function invalidState(): never
    {
        throw ValidationException::withMessages([
            'service_request' => ['Không thể thay đổi yêu cầu dịch vụ ở trạng thái hiện tại.'],
        ]);
    }
}
