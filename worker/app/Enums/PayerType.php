<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayerType: string implements HasLabel
{
    case Orderer = 'ORDERER';
    case Recipient = 'RECIPIENT';

    public function getLabel(): string
    {
        return match ($this) {
            self::Orderer => 'Người đặt',
            self::Recipient => 'Người nhận',
        };
    }
}
