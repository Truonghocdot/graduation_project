<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AssignmentFactory;
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
 * @property int $vehicle_id
 * @property int|null $accepted_offer_id
 * @property AssignmentStatus $status
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $closed_at
 * @property string|null $close_reason_code
 */
#[Fillable([
    'service_request_id',
    'driver_profile_id',
    'vehicle_id',
    'accepted_offer_id',
    'status',
    'assigned_at',
    'closed_at',
    'close_reason_code',
])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Assignment $assignment): void {
            $assignment->public_id ??= (string) Str::uuid();
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

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<DriverOffer, $this> */
    public function acceptedOffer(): BelongsTo
    {
        return $this->belongsTo(DriverOffer::class, 'accepted_offer_id');
    }

    /** @return HasOne<ChatConversation, $this> */
    public function chatConversation(): HasOne
    {
        return $this->hasOne(ChatConversation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'assigned_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
