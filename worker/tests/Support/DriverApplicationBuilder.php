<?php

namespace Tests\Support;

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

final class DriverApplicationBuilder
{
    /**
     * @param  array<int, ServiceType>  $services
     */
    public static function submitted(
        User $user,
        VehicleType $vehicleType,
        array $services = [ServiceType::Delivery],
    ): DriverProfile {
        $profile = DriverProfile::query()->create([
            'user_id' => $user->id,
            'review_status' => DriverReviewStatus::PendingReview,
            'availability_status' => DriverAvailabilityStatus::Offline,
            'submitted_at' => now(),
        ]);

        $vehicle = Vehicle::query()->create([
            'driver_profile_id' => $profile->id,
            'vehicle_type_id' => $vehicleType->id,
            'plate_number' => '59A1'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'brand' => 'Honda',
            'model' => 'Wave',
            'color' => 'Black',
            'status' => ReviewableStatus::Pending,
            'is_selected' => true,
        ]);

        foreach (DriverDocumentType::cases() as $documentType) {
            DriverDocument::query()->create([
                'driver_profile_id' => $profile->id,
                'vehicle_id' => $documentType->belongsToVehicle() ? $vehicle->id : null,
                'document_type' => $documentType,
                'document_number' => in_array($documentType, [
                    DriverDocumentType::Identity,
                    DriverDocumentType::DriverLicense,
                    DriverDocumentType::VehicleRegistration,
                ], true) ? $documentType->value.'-'.$user->id : null,
                'file_path' => 'test/'.$documentType->value.'.jpg',
                'expires_at' => now()->addYear()->toDateString(),
                'status' => ReviewableStatus::Pending,
            ]);
        }

        foreach ($services as $service) {
            $profile->capabilities()->create([
                'vehicle_type_id' => $vehicleType->id,
                'service_type' => $service,
                'is_active' => false,
            ]);
        }

        return $profile->load(['user', 'documents', 'vehicles', 'capabilities']);
    }
}
