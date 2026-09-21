<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleType */
class VehicleTypeResource extends JsonResource
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
            'key' => $this->unique_key,
            'name' => $this->name,
            'passenger_capacity' => $this->passenger_capacity,
            'max_weight_kg' => $this->max_weight_kg,
            'max_dimensions_cm' => [
                'length' => $this->max_length_cm,
                'width' => $this->max_width_cm,
                'height' => $this->max_height_cm,
            ],
        ];
    }
}
