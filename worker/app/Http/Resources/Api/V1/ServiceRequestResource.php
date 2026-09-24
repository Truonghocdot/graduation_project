<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceRequest;
use App\Support\ServiceStatusMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceRequest */
class ServiceRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'service_type' => $this->service_type->value,
            'status' => $this->status->value,
            'status_meta' => ServiceStatusMetadata::for($this->service_type, $this->status),
            'booking_type' => $this->booking_type->value,
            'scheduled_at' => $this->scheduled_at,
            'search_started_at' => $this->search_started_at,
            'vehicle_type' => new VehicleTypeResource($this->whenLoaded('vehicleType')),
            'quote_id' => $this->whenLoaded('quote', fn () => $this->quote->public_id),
            'stops' => ServiceStopResource::collection($this->whenLoaded('stops')),
            'delivery_order' => $this->whenLoaded('deliveryOrder', fn () => [
                'payer_type' => $this->deliveryOrder->payer_type->value,
                'goods_type' => $this->deliveryOrder->goods_type,
                'goods_description' => $this->deliveryOrder->goods_description,
                'weight_kg' => $this->deliveryOrder->weight_kg,
                'dimensions_cm' => [
                    'length' => $this->deliveryOrder->length_cm,
                    'width' => $this->deliveryOrder->width_cm,
                    'height' => $this->deliveryOrder->height_cm,
                ],
                'declared_value' => $this->deliveryOrder->declared_value,
                'is_cod' => $this->deliveryOrder->is_cod,
                'cod_amount' => $this->deliveryOrder->cod_amount,
            ]),
            'ride_booking' => $this->whenLoaded('rideBooking', fn () => [
                'passenger_count' => $this->rideBooking->passenger_count,
                'route_version' => $this->rideBooking->route_version,
            ]),
            'payment' => $this->whenLoaded('payment', fn () => [
                'id' => $this->payment->public_id,
                'method' => $this->payment->method->value,
                'status' => $this->payment->status->value,
                'gross_fare' => $this->payment->gross_fare,
                'voucher_discount' => $this->payment->voucher_discount,
                'customer_payable' => $this->payment->customer_payable,
                'currency' => $this->payment->currency,
                'settlement' => $this->payment->relationLoaded('settlement')
                    && $this->payment->settlement !== null
                        ? new SettlementResource($this->payment->settlement)
                        : null,
            ]),
            'assignment' => $this->whenLoaded('assignments', function () {
                $assignment = $this->assignments->first();

                return $assignment === null ? null : new AssignmentResource($assignment);
            }),
            'evidences' => ServiceEvidenceResource::collection(
                $this->whenLoaded('evidences'),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
