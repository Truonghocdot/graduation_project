<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $requested_by
 * @property ServiceType $service_type
 * @property int $vehicle_type_id
 * @property int $pricing_rule_id
 * @property BookingType $booking_type
 * @property CarbonImmutable|null $scheduled_at
 * @property array<string, mixed> $pickup_snapshot
 * @property array<string, mixed> $dropoff_snapshot
 * @property array<string, mixed> $service_payload
 * @property array<string, mixed> $route_snapshot
 * @property float $distance_meters
 * @property int $duration_seconds
 * @property float $base_fare
 * @property float $extra_distance_fare
 * @property float $surcharge_amount
 * @property float $gross_fare
 * @property float $voucher_discount
 * @property float $customer_payable
 * @property float $driver_rate
 * @property string $currency
 * @property QuoteStatus $status
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'requested_by',
    'service_type',
    'vehicle_type_id',
    'pricing_rule_id',
    'booking_type',
    'scheduled_at',
    'pickup_snapshot',
    'dropoff_snapshot',
    'service_payload',
    'route_snapshot',
    'distance_meters',
    'duration_seconds',
    'base_fare',
    'extra_distance_fare',
    'surcharge_amount',
    'gross_fare',
    'voucher_discount',
    'customer_payable',
    'driver_rate',
    'currency',
    'status',
    'expires_at',
    'used_at',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (Quote $quote): void {
            $quote->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<VehicleType, $this> */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    /** @return BelongsTo<PricingRule, $this> */
    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'booking_type' => BookingType::class,
            'scheduled_at' => 'immutable_datetime',
            'pickup_snapshot' => 'array',
            'dropoff_snapshot' => 'array',
            'service_payload' => 'array',
            'route_snapshot' => 'array',
            'distance_meters' => 'float',
            'duration_seconds' => 'integer',
            'base_fare' => 'float',
            'extra_distance_fare' => 'float',
            'surcharge_amount' => 'float',
            'gross_fare' => 'float',
            'voucher_discount' => 'float',
            'customer_payable' => 'float',
            'driver_rate' => 'float',
            'status' => QuoteStatus::class,
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
