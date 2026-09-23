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
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function saveDraft(User $user): DriverProfile
    {
        $profile = DriverProfile::query()->firstOrNew(['user_id' => $user->id]);

        if ($profile->exists && ! $profile->isEditable()) {
            $this->throwNotEditable();
        }

        $profile->fill([
            'review_status' => DriverReviewStatus::Draft,
            'availability_status' => DriverAvailabilityStatus::Offline,
            'review_reason_code' => null,
        ])->save();

        return $this->load($profile);
    }

    public function profileFor(User $user): DriverProfile
    {
        $profile = DriverProfile::query()->whereBelongsTo($user)->first();

        if ($profile === null) {
            throw ValidationException::withMessages([
                'application' => ['Chưa bắt đầu hồ sơ đăng ký tài xế.'],
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
                'document_number' => ['Loại giấy tờ này yêu cầu số giấy tờ.'],
            ]);
        }

        $documentNumber = filled($attributes['document_number'] ?? null)
            ? mb_strtoupper(trim((string) $attributes['document_number']))
            : null;

        $this->ensureDocumentNumberIsAvailable($profile, $documentType, $documentNumber);

        $path = Storage::disk('local')->putFile(
            "drivers/{$profile->public_id}/documents",
            $file,
        );

        if ($path === false) {
            throw new RuntimeException('Không thể lưu giấy tờ của tài xế.');
        }

        try {
            [$document, $previousPath] = DB::transaction(function () use (
                $profile,
                $documentType,
                $vehicle,
                $documentNumber,
                $attributes,
                $path,
            ): array {
                $existing = $profile->documents()
                    ->where('document_type', $documentType->value)
                    ->when(
                        $vehicle === null,
                        fn ($query) => $query->whereNull('vehicle_id'),
                        fn ($query) => $query->where('vehicle_id', $vehicle->id),
                    )
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $previousPath = $existing->file_path;
                    $existing->fill([
                        'document_number' => $documentNumber,
                        'file_path' => $path,
                        'expires_at' => $attributes['expires_at'] ?? null,
                        'status' => ReviewableStatus::Pending,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ])->save();

                    return [$existing->load('vehicle'), $previousPath];
                }

                $document = DriverDocument::query()->create([
                    'driver_profile_id' => $profile->id,
                    'vehicle_id' => $vehicle?->id,
                    'document_type' => $documentType,
                    'document_number' => $documentNumber,
                    'file_path' => $path,
                    'expires_at' => $attributes['expires_at'] ?? null,
                    'status' => ReviewableStatus::Pending,
                ])->load('vehicle');

                return [$document, null];
            });
        } catch (UniqueConstraintViolationException $exception) {
            Storage::disk('local')->delete($path);

            throw ValidationException::withMessages([
                'document_number' => ['So giay to nay da duoc dung boi mot ho so tai xe khac.'],
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return $document;
    }

    private function ensureDocumentNumberIsAvailable(
        DriverProfile $profile,
        DriverDocumentType $documentType,
        ?string $documentNumber,
    ): void {
        if ($documentNumber === null) {
            return;
        }

        $usedByAnotherProfile = DriverDocument::query()
            ->where('document_type', $documentType->value)
            ->where('document_number', $documentNumber)
            ->whereIn('status', [
                ReviewableStatus::Pending->value,
                ReviewableStatus::Approved->value,
            ])
            ->where('driver_profile_id', '<>', $profile->id)
            ->exists();

        if ($usedByAnotherProfile) {
            throw ValidationException::withMessages([
                'document_number' => ['So giay to nay da duoc dung boi mot ho so tai xe khac.'],
            ]);
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
        return DB::transaction(function () use ($user, $attributes): Vehicle {
            $profile = DriverProfile::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                throw ValidationException::withMessages([
                    'application' => ['Chưa bắt đầu hồ sơ đăng ký tài xế.'],
                ]);
            }
            if (! $profile->isEditable()) {
                $this->throwNotEditable();
            }
            if ($profile->vehicles()->exists()) {
                throw ValidationException::withMessages([
                    'vehicle_type_id' => ['Mỗi hồ sơ tài xế chỉ được đăng ký một phương tiện.'],
                ]);
            }

            $vehicleType = $this->vehicleType($attributes['vehicle_type_id']);

            return Vehicle::query()->create([
                'driver_profile_id' => $profile->id,
                'vehicle_type_id' => $vehicleType->id,
                'plate_number' => $attributes['plate_number'],
                'brand' => $attributes['brand'] ?? null,
                'model' => $attributes['model'] ?? null,
                'color' => $attributes['color'] ?? null,
                'status' => ReviewableStatus::Pending,
                'is_selected' => true,
            ])->load(['vehicleType', 'documents']);
        });
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
                    'vehicle_id' => ['Xe được chọn không thuộc hồ sơ này.'],
                ]);
            }

            $this->validateSubmissionDocuments($profile, $vehicle);
            $serviceEnums = collect($serviceTypes)->map(ServiceType::from(...));

            foreach ($serviceEnums as $serviceType) {
                if ($serviceType === ServiceType::Drive && $vehicle->vehicleType->passenger_capacity === null) {
                    throw ValidationException::withMessages([
                        'service_types' => ['Xe được chọn không hỗ trợ dịch vụ đặt xe.'],
                    ]);
                }

                if ($serviceType === ServiceType::Delivery && $vehicle->vehicleType->max_weight_kg === null) {
                    throw ValidationException::withMessages([
                        'service_types' => ['Xe được chọn không hỗ trợ dịch vụ giao hàng.'],
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
                    'vehicle_id' => ['Loại giấy tờ này không được gắn với xe.'],
                ]);
            }

            return null;
        }

        $vehicle = $profile->vehicles()->where('public_id', $vehiclePublicId)->first();

        if ($vehicle === null) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Cần chọn xe thuộc hồ sơ này.'],
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
                'documents' => ['Thiếu giấy tờ bắt buộc: '.$missing
                    ->map(fn (string $type): string => DriverDocumentType::from($type)->getLabel())
                    ->implode(', ').'.'],
            ]);
        }

        if ($documents->contains(fn (DriverDocument $document): bool => $document->expires_at?->isPast() === true)) {
            throw ValidationException::withMessages([
                'documents' => ['Không thể gửi xét duyệt khi có giấy tờ đã hết hạn.'],
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
            'application' => ['Không thể chỉnh sửa hồ sơ tài xế ở trạng thái hiện tại.'],
        ]);
    }
}
