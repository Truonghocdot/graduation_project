<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DriverOfferStatus: string implements HasLabel
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Chờ phản hồi',
            self::Accepted => 'Đã nhận',
            self::Declined => 'Đã từ chối',
            self::Expired => 'Đã hết hạn',
            self::Cancelled => 'Đã hủy',
        };
    }
}
