<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Wallet = 'WALLET';
    case Cash = 'CASH';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wallet => 'Ví',
            self::Cash => 'Tiền mặt',
        };
    }
}
