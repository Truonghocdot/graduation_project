<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceStop */
class ServiceStopResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->stop_type->value,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'note' => $this->note,
        ];
    }
}
