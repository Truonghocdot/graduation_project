<?php

namespace App\Services\Driver;

use App\Contracts\Matching\DriverPresenceStore;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverDocumentType;
use App\Enums\DriverReviewStatus;
use App\Enums\ReviewableStatus;
use App\Models\DriverLastLocation;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverAvailabilityService
{
    public function __construct(private readonly DriverPresenceStore $presenceStore) {}

    private const REQUIRED_DOCUMENTS = [
        DriverDocumentType::Identity,
        DriverDocumentType::DriverLicense,
        DriverDocumentType::Portrait,
        DriverDocumentType::VehicleRegistration,
        DriverDocumentType::Insurance,
        DriverDocumentType::VehiclePhoto,
    ];

    /**
     * @param  array{service_types: array<int, string>, latitude: float, longitude: float, accuracy: float, heading?: float|null, speed?: float|null, captured_at: string}  $attributes
     */
    public function goOnline(User $user, array $attributes): DriverProfile
    {
        return DB::transaction(function () use ($user, $attributes): DriverProfile {
            $profile = DriverProfile::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($profile === null || $profile->review_status !== DriverReviewStatus::Approved) {
                $this->throwEligibility('The driver profile is not approved.');
            }

            if ($profile->availability_status === DriverAvailabilityStatus::Busy) {
                $this->throwEligibility('A busy driver cannot change availability.');
            }

            if (DB::table('assignments')->where('driver_profile_id', $profile->id)->where('status', 'ACTIVE')->exists()) {
                $this->throwEligibility('The driver already has an active assignment.');
            }

            $walletBalance = $user->wallet()->value('balance');

            if ($walletBalance === null || (float) $walletBalance < 0) {
                $this->throwEligibility('The driver wallet is missing or has a negative balance.');
            }

            $vehicle = $profile->vehicles()
                ->where('is_selected', true)
                ->where('status', ReviewableStatus::Approved->value)
                ->first();

            if ($vehicle === null) {
                $this->throwEligibility('An approved selected vehicle is required.');
            }

            $requestedServices = collect($attributes['service_types']);
            $approvedServices = $profile->capabilities()
                ->where('vehicle_type_id', $vehicle->vehicle_type_id)
                ->where('is_active', true)
                ->get()
                ->map(fn ($capability): string => $capability->service_type->value);

            if ($requestedServices->diff($approvedServices)->isNotEmpty()) {
                $this->throwEligibility('One or more services are not approved for the selected vehicle.');
            }

            $this->validateDocuments($profile, $vehicle->id);
            $capturedAt = Carbon::parse($attributes['captured_at']);
            $lastLocation = DriverLastLocation::query()->find($profile->id);

            if ($lastLocation !== null && ! $capturedAt->isAfter($lastLocation->last_location_at)) {
                throw ValidationException::withMessages([
                    'captured_at' => ['The location sample is older than the current driver location.'],
                ]);
            }

            DriverLastLocation::query()->updateOrCreate(
                ['driver_profile_id' => $profile->id],
                [
                    'last_location' => [
                        'lat' => $attributes['latitude'],
                        'lng' => $attributes['longitude'],
                        'accuracy' => $attributes['accuracy'],
                        'heading' => $attributes['heading'] ?? null,
                        'speed' => $attributes['speed'] ?? null,
                    ],
                    'last_location_at' => $capturedAt,
                    'updated_at' => now(),
                ],
            );

            $this->presenceStore->markOnline(
                $profile->id,
                $requestedServices->values()->all(),
                (float) $attributes['latitude'],
                (float) $attributes['longitude'],
                (int) config('matching.presence_ttl_seconds', 15),
            );

            $profile->forceFill([
                'availability_status' => DriverAvailabilityStatus::Online,
                'online_at' => now(),
                'offline_at' => null,
            ])->save();

            return $this->load($profile);
        });
    }

    public function goOffline(User $user): DriverProfile
    {
        return DB::transaction(function () use ($user): DriverProfile {
            $profile = DriverProfile::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $profile->availability_status === DriverAvailabilityStatus::Busy
                || DB::table('assignments')->where('driver_profile_id', $profile->id)->where('status', 'ACTIVE')->exists()
            ) {
                $this->throwEligibility('A busy driver cannot go offline.');
            }

            $profile->forceFill([
                'availability_status' => DriverAvailabilityStatus::Offline,
                'offline_at' => now(),
            ])->save();
            $this->presenceStore->markOffline($profile->id);

            return $this->load($profile);
        });
    }

    private function validateDocuments(DriverProfile $profile, int $vehicleId): void
    {
        $documents = $profile->documents()
            ->where('status', ReviewableStatus::Approved->value)
            ->where(function ($query) use ($vehicleId): void {
                $query->whereNull('vehicle_id')->orWhere('vehicle_id', $vehicleId);
            })
            ->get();

        $approvedTypes = $documents->pluck('document_type')
            ->map(fn (DriverDocumentType $type): string => $type->value);
        $missing = collect(self::REQUIRED_DOCUMENTS)
            ->map(fn (DriverDocumentType $type): string => $type->value)
            ->diff($approvedTypes);

        if ($missing->isNotEmpty()) {
            $this->throwEligibility('Required approved documents are missing.');
        }

        if ($documents->contains(fn ($document): bool => $document->expires_at?->isPast() === true)) {
            $this->throwEligibility('One or more documents have expired.');
        }
    }

    private function load(DriverProfile $profile): DriverProfile
    {
        return $profile->load([
            'user',
            'documents.vehicle',
            'vehicles.vehicleType',
            'vehicles.documents.vehicle',
            'capabilities.vehicleType',
            'lastLocation',
        ]);
    }

    private function throwEligibility(string $message): never
    {
        throw ValidationException::withMessages([
            'availability' => [$message],
        ]);
    }
}
