<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReviewableStatus: string implements HasLabel
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Suspended = 'SUSPENDED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xét duyệt',
            self::Approved => 'Đã phê duyệt',
            self::Rejected => 'Đã từ chối',
            self::Expired => 'Đã hết hạn',
            self::Suspended => 'Tạm ngưng',
        };
    }
}
