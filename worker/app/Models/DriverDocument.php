<?php

namespace App\Models;

use App\Enums\DriverDocumentType;
use App\Enums\ReviewableStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $driver_profile_id
 * @property int|null $vehicle_id
 * @property DriverDocumentType $document_type
 * @property string|null $document_number
 * @property string $file_path
 * @property CarbonImmutable|null $expires_at
 * @property ReviewableStatus $status
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable([
    'driver_profile_id',
    'vehicle_id',
    'document_type',
    'document_number',
    'file_path',
    'expires_at',
    'status',
    'reviewed_by',
    'reviewed_at',
])]
class DriverDocument extends Model
{
    protected static function booted(): void
    {
        static::creating(function (DriverDocument $document): void {
            $document->public_id ??= (string) Str::uuid();
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

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => DriverDocumentType::class,
            'status' => ReviewableStatus::class,
            'expires_at' => 'immutable_date',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
