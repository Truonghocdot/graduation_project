<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DriverDocumentType: string implements HasLabel
{
    case Identity = 'IDENTITY';
    case DriverLicense = 'DRIVER_LICENSE';
    case VehicleRegistration = 'VEHICLE_REGISTRATION';
    case Insurance = 'INSURANCE';
    case Portrait = 'PORTRAIT';
    case VehiclePhoto = 'VEHICLE_PHOTO';

    public function getLabel(): string
    {
        return match ($this) {
            self::Identity => 'Căn cước công dân',
            self::DriverLicense => 'Giấy phép lái xe',
            self::VehicleRegistration => 'Đăng ký xe',
            self::Insurance => 'Bảo hiểm',
            self::Portrait => 'Ảnh chân dung',
            self::VehiclePhoto => 'Ảnh xe',
        };
    }

    public function belongsToVehicle(): bool
    {
        return in_array($this, [
            self::VehicleRegistration,
            self::Insurance,
            self::VehiclePhoto,
        ], true);
    }
}
