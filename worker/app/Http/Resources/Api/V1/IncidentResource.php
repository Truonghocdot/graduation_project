<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Incident */
class IncidentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'service_request_id' => $this->serviceRequest?->public_id,
            'incident_type' => $this->incident_type,
            'severity' => $this->severity,
            'status' => $this->status->value,
            'description' => $this->description,
            'resolution_code' => $this->resolution_code,
            'created_at' => $this->created_at,
            'resolved_at' => $this->resolved_at,
        ];
    }
}
