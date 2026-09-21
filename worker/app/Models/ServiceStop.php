<?php

namespace App\Models;

use App\Enums\StopType;
use Database\Factories\ServiceStopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property StopType $stop_type */
#[Fillable([
    'service_request_id',
    'stop_type',
    'address',
    'latitude',
    'longitude',
    'contact_name',
    'contact_phone',
    'note',
])]
class ServiceStop extends Model
{
    /** @use HasFactory<ServiceStopFactory> */
    use HasFactory;

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stop_type' => StopType::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
