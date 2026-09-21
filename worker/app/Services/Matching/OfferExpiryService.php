<?php

namespace App\Services\Matching;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverOfferStatus;
use App\Models\DriverOffer;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

class OfferExpiryService
{
    public function expire(): int
    {
        $expired = 0;

        DriverOffer::query()
            ->where('status', DriverOfferStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($offers) use (&$expired): void {
                foreach ($offers as $candidate) {
                    DB::transaction(function () use ($candidate, &$expired): void {
                        $offer = DriverOffer::query()->lockForUpdate()->find($candidate->id);

                        if ($offer === null || $offer->status !== DriverOfferStatus::Pending || $offer->expires_at->isFuture()) {
                            return;
                        }

                        $offer->forceFill([
                            'status' => DriverOfferStatus::Expired,
                            'responded_at' => now(),
                        ])->save();
                        $offer->driverProfile()->increment('ignored_offer_count');
                        $offer->driverProfile()->update([
                            'availability_status' => DriverAvailabilityStatus::Online,
                        ]);
                        OutboxEvent::query()->create([
                            'event_type' => 'OFFER_EXPIRED',
                            'aggregate_type' => 'SERVICE_REQUEST',
                            'aggregate_id' => $offer->service_request_id,
                            'aggregate_version' => $offer->serviceRequest->version,
                            'payload' => [
                                'offer_id' => $offer->public_id,
                                'service_request_id' => $offer->serviceRequest->public_id,
                                'driver_profile_id' => $offer->driverProfile->public_id,
                            ],
                            'status' => 'PENDING',
                            'attempt_count' => 0,
                            'available_at' => now(),
                        ]);
                        $expired++;
                    });
                }
            });

        return $expired;
    }
}
