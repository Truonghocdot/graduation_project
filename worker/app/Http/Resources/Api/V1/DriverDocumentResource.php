<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DriverDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DriverDocument */
class DriverDocumentResource extends JsonResource
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
            'type' => $this->document_type->value,
            'document_number' => $this->document_number,
            'vehicle_id' => $this->vehicle?->public_id,
            'expires_at' => $this->expires_at?->toDateString(),
            'status' => $this->status->value,
            'file_url' => route('api.v1.driver-documents.file', $this->resource, absolute: false),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
