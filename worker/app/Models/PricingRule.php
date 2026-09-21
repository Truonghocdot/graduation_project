<?php

namespace App\Models;

use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Database\Factories\PricingRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property ServiceType $service_type
 * @property int $vehicle_type_id
 * @property float $base_distance_km
 * @property float $base_fare
 * @property float $price_per_extra_km
 * @property float $driver_rate
 * @property string $currency
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property bool $is_active
 * @property int $created_by
 */
#[Fillable([
    'service_type',
    'vehicle_type_id',
    'base_distance_km',
    'base_fare',
    'price_per_extra_km',
    'driver_rate',
    'currency',
    'effective_from',
    'effective_to',
    'is_active',
    'created_by',
])]
class PricingRule extends Model
{
    /** @use HasFactory<PricingRuleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PricingRule $pricingRule): void {
            $pricingRule->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<VehicleType, $this> */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'base_distance_km' => 'float',
            'base_fare' => 'float',
            'price_per_extra_km' => 'float',
            'driver_rate' => 'float',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }
}
