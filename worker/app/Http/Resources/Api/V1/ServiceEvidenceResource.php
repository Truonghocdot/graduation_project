<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceEvidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceEvidence */
class ServiceEvidenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->evidence_type->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sha256' => $this->sha256,
            'metadata' => $this->metadata,
            'file_url' => route('api.v1.service-evidence.file', [
                'evidence' => $this->public_id,
            ], false),
            'created_at' => $this->created_at,
        ];
    }
}
