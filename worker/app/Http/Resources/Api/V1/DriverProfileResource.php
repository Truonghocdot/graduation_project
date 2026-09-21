<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DriverProfile */
class DriverProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->public_id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
            ]),
            'review_status' => $this->review_status->value,
            'availability_status' => $this->availability_status->value,
            'review_reason_code' => $this->review_reason_code,
            'cod_limit' => $this->cod_limit,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'documents' => DriverDocumentResource::collection($this->whenLoaded('documents')),
            'vehicles' => VehicleResource::collection($this->whenLoaded('vehicles')),
            'capabilities' => $this->whenLoaded(
                'capabilities',
                fn () => $this->capabilities->map(fn ($capability) => [
                    'service_type' => $capability->service_type->value,
                    'vehicle_type_id' => $capability->vehicleType?->public_id,
                    'is_active' => $capability->is_active,
                ])->values(),
            ),
            'last_location' => $this->whenLoaded(
                'lastLocation',
                fn () => $this->lastLocation === null ? null : [
                    ...$this->lastLocation->last_location,
                    'captured_at' => $this->lastLocation->last_location_at->toISOString(),
                ],
            ),
        ];
    }
}
