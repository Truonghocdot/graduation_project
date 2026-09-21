<?php

namespace App\Models;

use App\Enums\ReviewableStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $driver_profile_id
 * @property int $vehicle_type_id
 * @property string $plate_number
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $color
 * @property ReviewableStatus $status
 * @property bool $is_selected
 * @property CarbonImmutable|null $created_at
 */
#[Fillable([
    'driver_profile_id',
    'vehicle_type_id',
    'plate_number',
    'brand',
    'model',
    'color',
    'status',
    'is_selected',
])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle): void {
            $vehicle->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<DriverProfile, $this> */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /** @return BelongsTo<VehicleType, $this> */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    /** @return HasMany<DriverDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReviewableStatus::class,
            'is_selected' => 'boolean',
        ];
    }
}
