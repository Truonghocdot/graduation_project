<?php

namespace App\Models;

use App\Enums\DriverOfferStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DriverOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $service_request_id
 * @property int $driver_profile_id
 * @property int $batch_number
 * @property DriverOfferStatus $status
 * @property float $estimated_pickup_distance_meters
 * @property int $estimated_pickup_seconds
 * @property float $estimated_driver_earning
 * @property CarbonImmutable $offered_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $responded_at
 */
#[Fillable([
    'service_request_id',
    'driver_profile_id',
    'batch_number',
    'status',
    'estimated_pickup_distance_meters',
    'estimated_pickup_seconds',
    'estimated_driver_earning',
    'offered_at',
    'expires_at',
    'responded_at',
])]
class DriverOffer extends Model
{
    /** @use HasFactory<DriverOfferFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (DriverOffer $offer): void {
            $offer->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<DriverProfile, $this> */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /** @return HasOne<Assignment, $this> */
    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class, 'accepted_offer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DriverOfferStatus::class,
            'estimated_pickup_distance_meters' => 'float',
            'estimated_pickup_seconds' => 'integer',
            'estimated_driver_earning' => 'float',
            'offered_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
        ];
    }
}
