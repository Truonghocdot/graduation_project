<?php

namespace App\Services\Driver;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverDocumentType;
use App\Enums\DriverReviewStatus;
use App\Enums\ReviewableStatus;
use App\Enums\ServiceType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DriverOnboardingService
{
    private const PERSONAL_DOCUMENTS = [
        DriverDocumentType::Identity,
        DriverDocumentType::DriverLicense,
        DriverDocumentType::Portrait,
    ];

    private const VEHICLE_DOCUMENTS = [
        DriverDocumentType::VehicleRegistration,
        DriverDocumentType::Insurance,
        DriverDocumentType::VehiclePhoto,
    ];

    public function saveDraft(User $user, float $codLimit): DriverProfile
    {
        $profile = DriverProfile::query()->firstOrNew(['user_id' => $user->id]);

        if ($profile->exists && ! $profile->isEditable()) {
            $this->throwNotEditable();
        }

        $profile->fill([
            'review_status' => DriverReviewStatus::Draft,
            'availability_status' => DriverAvailabilityStatus::Offline,
            'cod_limit' => $codLimit,
            'review_reason_code' => null,
        ])->save();

        return $this->load($profile);
    }

    public function profileFor(User $user): DriverProfile
    {
        $profile = DriverProfile::query()->whereBelongsTo($user)->first();

        if ($profile === null) {
            throw ValidationException::withMessages([
                'application' => ['A driver application has not been started.'],
            ]);
        }

        return $this->load($profile);
    }

    /**
     * @param  array{document_type: string, document_number?: string|null, vehicle_id?: string|null, expires_at?: string|null}  $attributes
     */
    public function addDocument(
        User $user,
        array $attributes,
        UploadedFile $file,
    ): DriverDocument {
        $profile = $this->editableProfileFor($user);
        $documentType = DriverDocumentType::from($attributes['document_type']);
        $vehicle = $this->resolveDocumentVehicle($profile, $documentType, $attributes['vehicle_id'] ?? null);

        if (
            in_array($documentType, [
                DriverDocumentType::Identity,
                DriverDocumentType::DriverLicense,
                DriverDocumentType::VehicleRegistration,
            ], true)
            && blank($attributes['document_number'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'document_number' => ['A document number is required for this document type.'],
            ]);
        }

        $path = Storage::disk('local')->putFile(
            "drivers/{$profile->public_id}/documents",
            $file,
        );

        if ($path === false) {
            throw new RuntimeException('The driver document could not be stored.');
        }

        try {
            return DriverDocument::query()->create([
                'driver_profile_id' => $profile->id,
                'vehicle_id' => $vehicle?->id,
                'document_type' => $documentType,
                'document_number' => filled($attributes['document_number'] ?? null)
                    ? mb_strtoupper(trim((string) $attributes['document_number']))
                    : null,
                'file_path' => $path,
                'expires_at' => $attributes['expires_at'] ?? null,
                'status' => ReviewableStatus::Pending,
            ])->load('vehicle');
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function removeDocument(User $user, DriverDocument $document): void
    {
        abort_unless(
            $document->driverProfile()->where('user_id', $user->id)->exists(),
            404,
        );
        $profile = $this->editableProfileFor($user);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }

    /**
     * @param  array{vehicle_type_id: string, plate_number: string, brand?: string|null, model?: string|null, color?: string|null}  $attributes
     */
    public function addVehicle(User $user, array $attributes): Vehicle
    {
        $profile = $this->editableProfileFor($user);
        $vehicleType = $this->vehicleType($attributes['vehicle_type_id']);
        $hasVehicle = $profile->vehicles()->exists();

        return Vehicle::query()->create([
            'driver_profile_id' => $profile->id,
            'vehicle_type_id' => $vehicleType->id,
            'plate_number' => $attributes['plate_number'],
            'brand' => $attributes['brand'] ?? null,
            'model' => $attributes['model'] ?? null,
            'color' => $attributes['color'] ?? null,
            'status' => ReviewableStatus::Pending,
            'is_selected' => ! $hasVehicle,
        ])->load(['vehicleType', 'documents']);
    }

    /**
     * @param  array{vehicle_type_id?: string, plate_number?: string, brand?: string|null, model?: string|null, color?: string|null}  $attributes
     */
    public function updateVehicle(User $user, Vehicle $vehicle, array $attributes): Vehicle
    {
        abort_unless(
            $vehicle->driverProfile()->where('user_id', $user->id)->exists(),
            404,
        );
        $profile = $this->editableProfileFor($user);

        if (isset($attributes['vehicle_type_id'])) {
            $vehicle->vehicle_type_id = $this->vehicleType($attributes['vehicle_type_id'])->id;
        }

        $vehicle->fill(collect($attributes)->except('vehicle_type_id')->all());
        $vehicle->status = ReviewableStatus::Pending;
        $vehicle->save();

        return $vehicle->load(['vehicleType', 'documents']);
    }

    public function selectVehicle(User $user, Vehicle $vehicle): Vehicle
    {
        abort_unless(
            $vehicle->driverProfile()->where('user_id', $user->id)->exists(),
            404,
        );
        $profile = $this->profileFor($user);

        if (! $profile->isEditable()) {
            if (
                $profile->review_status !== DriverReviewStatus::Approved
                || $profile->availability_status !== DriverAvailabilityStatus::Offline
                || $vehicle->status !== ReviewableStatus::Approved
            ) {
                $this->throwNotEditable();
            }
        }

        DB::transaction(function () use ($profile, $vehicle): void {
            $profile->vehicles()->update(['is_selected' => false]);
            $vehicle->forceFill(['is_selected' => true])->save();
        });

        return $vehicle->load(['vehicleType', 'documents']);
    }

    /**
     * @param  array<int, string>  $serviceTypes
     */
    public function submit(User $user, string $vehiclePublicId, array $serviceTypes): DriverProfile
    {
        return DB::transaction(function () use ($user, $vehiclePublicId, $serviceTypes): DriverProfile {
            $profile = DriverProfile::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($profile === null || ! $profile->isEditable()) {
                $this->throwNotEditable();
            }

            $vehicle = $profile->vehicles()
                ->where('public_id', $vehiclePublicId)
                ->with('vehicleType')
                ->first();

            if ($vehicle === null) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['The selected vehicle does not belong to this application.'],
                ]);
            }

            $this->validateSubmissionDocuments($profile, $vehicle);
            $serviceEnums = collect($serviceTypes)->map(ServiceType::from(...));

            foreach ($serviceEnums as $serviceType) {
                if ($serviceType === ServiceType::Drive && $vehicle->vehicleType->passenger_capacity === null) {
                    throw ValidationException::withMessages([
                        'service_types' => ['The selected vehicle does not support Drive.'],
                    ]);
                }

                if ($serviceType === ServiceType::Delivery && $vehicle->vehicleType->max_weight_kg === null) {
                    throw ValidationException::withMessages([
                        'service_types' => ['The selected vehicle does not support Delivery.'],
                    ]);
                }

                $profile->capabilities()->updateOrCreate(
                    [
                        'vehicle_type_id' => $vehicle->vehicle_type_id,
                        'service_type' => $serviceType->value,
                    ],
                    ['is_active' => false, 'approved_by' => null, 'approved_at' => null],
                );
            }

            $profile->capabilities()
                ->where(function ($query) use ($vehicle, $serviceEnums): void {
                    $query->where('vehicle_type_id', '<>', $vehicle->vehicle_type_id)
                        ->orWhereNotIn(
                            'service_type',
                            $serviceEnums->map(fn (ServiceType $type): string => $type->value)->all(),
                        );
                })
                ->delete();

            $profile->vehicles()->update(['is_selected' => false]);
            $vehicle->forceFill(['is_selected' => true, 'status' => ReviewableStatus::Pending])->save();
            $profile->documents()->where('status', ReviewableStatus::Rejected->value)->update([
                'status' => ReviewableStatus::Pending->value,
            ]);
            $profile->forceFill([
                'review_status' => DriverReviewStatus::PendingReview,
                'availability_status' => DriverAvailabilityStatus::Offline,
                'review_reason_code' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
            ])->save();

            return $this->load($profile);
        });
    }

    private function editableProfileFor(User $user): DriverProfile
    {
        $profile = $this->profileFor($user);

        if (! $profile->isEditable()) {
            $this->throwNotEditable();
        }

        return $profile;
    }

    private function resolveDocumentVehicle(
        DriverProfile $profile,
        DriverDocumentType $type,
        ?string $vehiclePublicId,
    ): ?Vehicle {
        if (! $type->belongsToVehicle()) {
            if ($vehiclePublicId !== null) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['This document type is not attached to a vehicle.'],
                ]);
            }

            return null;
        }

        $vehicle = $profile->vehicles()->where('public_id', $vehiclePublicId)->first();

        if ($vehicle === null) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['A vehicle owned by this application is required.'],
            ]);
        }

        return $vehicle;
    }

    private function vehicleType(string $publicId): VehicleType
    {
        return VehicleType::query()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function validateSubmissionDocuments(DriverProfile $profile, Vehicle $vehicle): void
    {
        $documents = $profile->documents()
            ->whereIn('status', [ReviewableStatus::Pending->value, ReviewableStatus::Approved->value])
            ->get();

        $personalTypes = $documents
            ->whereNull('vehicle_id')
            ->pluck('document_type')
            ->map(fn (DriverDocumentType $type): string => $type->value);
        $vehicleTypes = $documents
            ->where('vehicle_id', $vehicle->id)
            ->pluck('document_type')
            ->map(fn (DriverDocumentType $type): string => $type->value);

        $missing = collect(self::PERSONAL_DOCUMENTS)
            ->map(fn (DriverDocumentType $type): string => $type->value)
            ->diff($personalTypes)
            ->merge(
                collect(self::VEHICLE_DOCUMENTS)
                    ->map(fn (DriverDocumentType $type): string => $type->value)
                    ->diff($vehicleTypes),
            )
            ->values();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'documents' => ['Missing required documents: '.$missing->implode(', ').'.'],
            ]);
        }

        if ($documents->contains(fn (DriverDocument $document): bool => $document->expires_at?->isPast() === true)) {
            throw ValidationException::withMessages([
                'documents' => ['Expired documents cannot be submitted.'],
            ]);
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

    private function throwNotEditable(): never
    {
        throw ValidationException::withMessages([
            'application' => ['The driver application cannot be edited in its current state.'],
        ]);
    }
}
