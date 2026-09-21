<?php

namespace App\Models;

use Database\Factories\RideBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_request_id',
    'passenger_count',
    'started_at',
    'ended_at',
    'route_version',
])]
class RideBooking extends Model
{
    /** @use HasFactory<RideBookingFactory> */
    use HasFactory;

    protected $primaryKey = 'service_request_id';

    public $incrementing = false;

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'passenger_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'route_version' => 'integer',
        ];
    }
}
