<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupportPriority: string implements HasLabel
{
    case Low = 'LOW';
    case Normal = 'NORMAL';
    case High = 'HIGH';
    case Urgent = 'URGENT';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Normal => 'Bình thường',
            self::High => 'Cao',
            self::Urgent => 'Khẩn cấp',
        };
    }
}
