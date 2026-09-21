<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $unique_key
 * @property string $name
 * @property int|null $passenger_capacity
 * @property float|null $max_weight_kg
 * @property float|null $max_length_cm
 * @property float|null $max_width_cm
 * @property float|null $max_height_cm
 * @property bool $is_active
 */
#[Fillable([
    'unique_key',
    'name',
    'passenger_capacity',
    'max_weight_kg',
    'max_length_cm',
    'max_width_cm',
    'max_height_cm',
    'is_active',
])]
class VehicleType extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (VehicleType $vehicleType): void {
            $vehicleType->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_weight_kg' => 'float',
            'max_length_cm' => 'float',
            'max_width_cm' => 'float',
            'max_height_cm' => 'float',
        ];
    }
}
