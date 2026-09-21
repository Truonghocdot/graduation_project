<?php

namespace App\Models;

use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $driver_profile_id
 * @property int $vehicle_type_id
 * @property ServiceType $service_type
 * @property bool $is_active
 * @property CarbonImmutable|null $approved_at
 */
#[Fillable([
    'driver_profile_id',
    'vehicle_type_id',
    'service_type',
    'is_active',
    'approved_by',
    'approved_at',
])]
class DriverServiceCapability extends Model
{
    public $timestamps = false;

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'is_active' => 'boolean',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
