<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RatingModerationStatus: string implements HasLabel
{
    case Visible = 'VISIBLE';
    case Hidden = 'HIDDEN';
    case Flagged = 'FLAGGED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Visible => 'Hiển thị',
            self::Hidden => 'Đã ẩn',
            self::Flagged => 'Bị gắn cờ',
        };
    }
}
