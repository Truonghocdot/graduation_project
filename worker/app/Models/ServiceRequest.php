<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property ServiceType $service_type
 * @property int $created_by
 * @property int $vehicle_type_id
 * @property int $quote_id
 * @property ServiceRequestStatus $status
 * @property BookingType $booking_type
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $search_started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason_code
 * @property int $search_attempt
 * @property int $version
 */
#[Fillable([
    'service_type',
    'created_by',
    'vehicle_type_id',
    'quote_id',
    'status',
    'booking_type',
    'scheduled_at',
    'search_started_at',
    'completed_at',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason_code',
    'search_attempt',
    'version',
])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ServiceRequest $request): void {
            $request->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<VehicleType, $this> */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return HasMany<ServiceStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(ServiceStop::class);
    }

    /** @return HasOne<DeliveryOrder, $this> */
    public function deliveryOrder(): HasOne
    {
        return $this->hasOne(DeliveryOrder::class);
    }

    /** @return HasOne<RideBooking, $this> */
    public function rideBooking(): HasOne
    {
        return $this->hasOne(RideBooking::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return HasMany<ServiceStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ServiceStatusHistory::class);
    }

    /** @return HasMany<DriverOffer, $this> */
    public function driverOffers(): HasMany
    {
        return $this->hasMany(DriverOffer::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasMany<ServiceEvidence, $this> */
    public function evidences(): HasMany
    {
        return $this->hasMany(ServiceEvidence::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => ServiceRequestStatus::class,
            'booking_type' => BookingType::class,
            'scheduled_at' => 'immutable_datetime',
            'search_started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'search_attempt' => 'integer',
            'version' => 'integer',
        ];
    }
}
