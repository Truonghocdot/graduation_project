<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quote */
class QuoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'service_type' => $this->service_type->value,
            'vehicle_type' => new VehicleTypeResource($this->whenLoaded('vehicleType')),
            'pricing_rule_id' => $this->pricingRule->public_id,
            'booking_type' => $this->booking_type->value,
            'scheduled_at' => $this->scheduled_at,
            'pickup' => $this->pickup_snapshot,
            'dropoff' => $this->dropoff_snapshot,
            'service_payload' => $this->service_payload,
            'route' => $this->route_snapshot,
            'pricing' => [
                'base_fare' => $this->base_fare,
                'extra_distance_fare' => $this->extra_distance_fare,
                'surcharge_amount' => $this->surcharge_amount,
                'gross_fare' => $this->gross_fare,
                'voucher_discount' => $this->voucher_discount,
                'customer_payable' => $this->customer_payable,
                'driver_rate' => $this->driver_rate,
                'currency' => $this->currency,
            ],
            'status' => $this->status->value,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
