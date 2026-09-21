<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $driver_profile_id
 * @property array<string, float|null> $last_location
 * @property CarbonImmutable $last_location_at
 */
#[Fillable(['driver_profile_id', 'last_location', 'last_location_at', 'updated_at'])]
class DriverLastLocation extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'driver_profile_id';

    /** @return BelongsTo<DriverProfile, $this> */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_location' => 'array',
            'last_location_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
