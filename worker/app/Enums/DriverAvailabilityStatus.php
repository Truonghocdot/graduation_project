<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DriverAvailabilityStatus: string implements HasLabel
{
    case Offline = 'OFFLINE';
    case Online = 'ONLINE';
    case Offered = 'OFFERED';
    case Busy = 'BUSY';

    public function getLabel(): string
    {
        return match ($this) {
            self::Offline => 'Ngoại tuyến',
            self::Online => 'Trực tuyến',
            self::Offered => 'Đang nhận đề nghị',
            self::Busy => 'Đang bận',
        };
    }
}
