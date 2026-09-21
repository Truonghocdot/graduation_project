<?php

namespace App\Enums;

enum DriverDocumentType: string
{
    case Identity = 'IDENTITY';
    case DriverLicense = 'DRIVER_LICENSE';
    case VehicleRegistration = 'VEHICLE_REGISTRATION';
    case Insurance = 'INSURANCE';
    case Portrait = 'PORTRAIT';
    case VehiclePhoto = 'VEHICLE_PHOTO';

    public function belongsToVehicle(): bool
    {
        return in_array($this, [
            self::VehicleRegistration,
            self::Insurance,
            self::VehiclePhoto,
        ], true);
    }
}
