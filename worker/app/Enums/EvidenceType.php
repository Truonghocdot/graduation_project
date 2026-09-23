<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EvidenceType: string implements HasLabel
{
    case Pickup = 'PICKUP';
    case Delivery = 'DELIVERY';
    case PassengerConfirmation = 'PASSENGER_CONFIRMATION';
    case Incident = 'INCIDENT';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pickup => 'Lấy hàng',
            self::Delivery => 'Giao hàng',
            self::PassengerConfirmation => 'Xác nhận hành khách',
            self::Incident => 'Sự cố',
        };
    }
}
