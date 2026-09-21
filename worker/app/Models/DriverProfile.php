<?php

namespace App\Models;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverReviewStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property DriverReviewStatus $review_status
 * @property DriverAvailabilityStatus $availability_status
 * @property string|null $review_reason_code
 * @property float $cod_limit
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $online_at
 * @property CarbonImmutable|null $offline_at
 */
#[Fillable([
    'user_id',
    'review_status',
    'availability_status',
    'review_reason_code',
    'reviewed_by',
    'reviewed_at',
    'submitted_at',
    'cod_limit',
    'online_at',
    'offline_at',
])]
class DriverProfile extends Model
{
    protected static function booted(): void
    {
        static::creating(function (DriverProfile $profile): void {
            $profile->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DriverDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return HasMany<DriverServiceCapability, $this> */
    public function capabilities(): HasMany
    {
        return $this->hasMany(DriverServiceCapability::class);
    }

    /** @return HasOne<DriverLastLocation, $this> */
    public function lastLocation(): HasOne
    {
        return $this->hasOne(DriverLastLocation::class);
    }

    /** @return HasMany<DriverBankAccount, $this> */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(DriverBankAccount::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->review_status, [
            DriverReviewStatus::Draft,
            DriverReviewStatus::Rejected,
        ], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'review_status' => DriverReviewStatus::class,
            'availability_status' => DriverAvailabilityStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'online_at' => 'immutable_datetime',
            'offline_at' => 'immutable_datetime',
            'cod_limit' => 'float',
        ];
    }
}
