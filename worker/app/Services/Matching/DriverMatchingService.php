<?php

namespace App\Services\Matching;

use App\Contracts\Matching\DriverPresenceStore;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverOfferStatus;
use App\Enums\DriverReviewStatus;
use App\Enums\ReviewableStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\DriverOffer;
use App\Models\DriverProfile;
use App\Models\OutboxEvent;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DriverMatchingService
{
    public function __construct(private readonly DriverPresenceStore $presenceStore) {}

    /** @return Collection<int, DriverOffer> */
    public function dispatch(ServiceRequest $serviceRequest): Collection
    {
        return DB::transaction(function () use ($serviceRequest): Collection {
            $request = ServiceRequest::query()
                ->with(['quote', 'deliveryOrder'])
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            if ($request->status !== ServiceRequestStatus::SearchingDriver) {
                return collect();
            }

            $pickup = $request->stops()->where('stop_type', 'PICKUP')->first();

            if ($pickup === null) {
                throw ValidationException::withMessages([
                    'matching' => ['A pickup stop is required before matching.'],
                ]);
            }

            $currentBatch = $request->search_attempt + 1;
            $pendingOffers = $request->driverOffers()
                ->where('status', DriverOfferStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingOffers) {
                return collect();
            }

            $nearby = $this->presenceStore->nearby(
                [$request->service_type->value],
                (float) $pickup->latitude,
                (float) $pickup->longitude,
                (float) config('matching.search_radius_meters', 5_000),
                (int) config('matching.batch_size', 5),
            );
            $profileIds = collect($nearby)->pluck('driver_profile_id')->map(
                fn (mixed $id): int => (int) $id,
            );
            $distanceByProfile = collect($nearby)->keyBy('driver_profile_id');
            $drivers = $this->eligibleDrivers($request, $profileIds);

            $offers = collect();
            foreach ($drivers as $profile) {
                $vehicle = $profile->vehicles->first();
                if ($vehicle === null) {
                    continue;
                }

                $distance = (float) ($distanceByProfile[$profile->id]['distance_meters'] ?? 0);
                $offer = DriverOffer::query()->create([
                    'service_request_id' => $request->id,
                    'driver_profile_id' => $profile->id,
                    'batch_number' => $currentBatch,
                    'status' => DriverOfferStatus::Pending,
                    'estimated_pickup_distance_meters' => $distance,
                    'estimated_pickup_seconds' => (int) round($distance / 8),
                    'estimated_driver_earning' => $request->quote->gross_fare * $request->quote->driver_rate,
                    'offered_at' => now(),
                    'expires_at' => now()->addSeconds((int) config('matching.offer_ttl_seconds', 30)),
                ]);
                $profile->increment('offer_count');
                $profile->forceFill([
                    'availability_status' => DriverAvailabilityStatus::Offered,
                ])->save();
                $offers->push($offer);

                OutboxEvent::query()->create([
                    'event_type' => 'OFFER_CREATED',
                    'aggregate_type' => 'SERVICE_REQUEST',
                    'aggregate_id' => $request->id,
                    'aggregate_version' => $request->version,
                    'payload' => [
                        'offer_id' => $offer->public_id,
                        'service_request_id' => $request->public_id,
                        'driver_profile_id' => $profile->public_id,
                        'batch_number' => $currentBatch,
                        'expires_at' => $offer->expires_at->toISOString(),
                    ],
                    'status' => 'PENDING',
                    'attempt_count' => 0,
                    'available_at' => now(),
                ]);
            }

            $request->forceFill(['search_attempt' => $currentBatch])->save();

            return $offers;
        });
    }

    public function activateDueScheduled(): int
    {
        $activated = 0;

        ServiceRequest::query()
            ->where('status', ServiceRequestStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($requests) use (&$activated): void {
                foreach ($requests as $candidate) {
                    DB::transaction(function () use ($candidate, &$activated): void {
                        $request = ServiceRequest::query()->lockForUpdate()->find($candidate->id);

                        if ($request === null
                            || $request->status !== ServiceRequestStatus::Scheduled
                            || $request->scheduled_at?->isFuture()) {
                            return;
                        }

                        $request->forceFill([
                            'status' => ServiceRequestStatus::SearchingDriver,
                            'search_started_at' => now(),
                            'version' => $request->version + 1,
                        ])->save();
                        ServiceStatusHistory::query()->create([
                            'service_request_id' => $request->id,
                            'version' => $request->version,
                            'from_status' => ServiceRequestStatus::Scheduled->value,
                            'to_status' => ServiceRequestStatus::SearchingDriver->value,
                            'actor_user_id' => null,
                            'actor_type' => 'SYSTEM',
                            'metadata' => [],
                            'correlation_id' => (string) Str::uuid(),
                            'created_at' => now(),
                        ]);
                        OutboxEvent::query()->create([
                            'event_type' => 'SCHEDULED_SEARCH_STARTED',
                            'aggregate_type' => 'SERVICE_REQUEST',
                            'aggregate_id' => $request->id,
                            'aggregate_version' => $request->version,
                            'payload' => [
                                'service_request_id' => $request->public_id,
                                'status' => ServiceRequestStatus::SearchingDriver->value,
                            ],
                            'status' => 'PENDING',
                            'attempt_count' => 0,
                            'available_at' => now(),
                        ]);
                        $activated++;
                    });
                }
            });

        return $activated;
    }

    /** @param Collection<int, int> $profileIds
     * @return Collection<int, DriverProfile>
     */
    private function eligibleDrivers(ServiceRequest $request, Collection $profileIds): Collection
    {
        if ($profileIds->isEmpty()) {
            return collect();
        }

        return DriverProfile::query()
            ->whereIn('id', $profileIds->all())
            ->where('review_status', DriverReviewStatus::Approved->value)
            ->where('availability_status', DriverAvailabilityStatus::Online->value)
            ->whereDoesntHave('assignments', fn ($query) => $query->where('status', 'ACTIVE'))
            ->whereHas('capabilities', function ($query) use ($request): void {
                $query->where('service_type', $request->service_type->value)
                    ->where('is_active', true);
            })
            ->with(['vehicles' => function ($query) use ($request): void {
                $query->where('vehicle_type_id', $request->vehicle_type_id)
                    ->where('status', ReviewableStatus::Approved->value)
                    ->where('is_selected', true);
            }])
            ->get()
            ->filter(fn (DriverProfile $profile): bool => $this->codEligible($profile, $request));
    }

    private function codEligible(DriverProfile $profile, ServiceRequest $request): bool
    {
        if ($request->service_type->value !== 'DELIVERY') {
            return true;
        }

        $codAmount = $request->deliveryOrder === null
            ? 0
            : (float) $request->deliveryOrder->cod_amount;

        return $codAmount <= $profile->cod_limit;
    }
}
