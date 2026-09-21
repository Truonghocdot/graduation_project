<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assignment */
class AssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'assigned_at' => $this->assigned_at,
            'driver_profile_id' => $this->driverProfile?->public_id,
            'vehicle_id' => $this->vehicle?->public_id,
            'service_request_id' => $this->serviceRequest?->public_id,
        ];
    }
}
