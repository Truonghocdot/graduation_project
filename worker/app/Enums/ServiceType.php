<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceType: string implements HasLabel
{
    case Delivery = 'DELIVERY';
    case Drive = 'DRIVE';

    public function getLabel(): string
    {
        return match ($this) {
            self::Delivery => 'Giao hàng',
            self::Drive => 'Đặt xe',
        };
    }
}
