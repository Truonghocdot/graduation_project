<?php

namespace App\Models;

use Database\Factories\ServiceStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_request_id',
    'version',
    'from_status',
    'to_status',
    'actor_user_id',
    'actor_type',
    'reason_code',
    'metadata',
    'correlation_id',
    'created_at',
])]
class ServiceStatusHistory extends Model
{
    /** @use HasFactory<ServiceStatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'metadata' => 'array',
            'correlation_id' => 'string',
            'created_at' => 'immutable_datetime',
        ];
    }
}
