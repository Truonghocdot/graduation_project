<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DriverOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DriverOffer */
class DriverOfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'batch_number' => $this->batch_number,
            'estimated_pickup_distance_meters' => $this->estimated_pickup_distance_meters,
            'estimated_pickup_seconds' => $this->estimated_pickup_seconds,
            'estimated_driver_earning' => $this->estimated_driver_earning,
            'offered_at' => $this->offered_at,
            'expires_at' => $this->expires_at,
            'responded_at' => $this->responded_at,
            'service_request' => new ServiceRequestResource($this->whenLoaded('serviceRequest')),
            'assignment' => $this->whenLoaded('assignment', fn () => [
                'id' => $this->assignment->public_id,
                'status' => $this->assignment->status->value,
                'assigned_at' => $this->assignment->assigned_at,
            ]),
        ];
    }
}
