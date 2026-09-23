<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel
{
    case PendingVerification = 'PENDING_VERIFICATION';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Closed = 'CLOSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingVerification => 'Chờ xác minh',
            self::Active => 'Đang hoạt động',
            self::Suspended => 'Tạm ngưng',
            self::Closed => 'Đã đóng',
        };
    }
}
