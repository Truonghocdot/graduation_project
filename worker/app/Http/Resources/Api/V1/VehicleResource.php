<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
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
            'vehicle_type' => VehicleTypeResource::make($this->whenLoaded('vehicleType')),
            'plate_number' => $this->plate_number,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'status' => $this->status->value,
            'is_selected' => $this->is_selected,
            'documents' => DriverDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
