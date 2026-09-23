<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingType: string implements HasLabel
{
    case Now = 'NOW';
    case Scheduled = 'SCHEDULED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Now => 'Ngay bây giờ',
            self::Scheduled => 'Đặt lịch',
        };
    }
}
