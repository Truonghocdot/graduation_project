<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SettlementStatus: string implements HasLabel
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Settled = 'SETTLED';
    case Failed = 'FAILED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xử lý',
            self::Processing => 'Đang xử lý',
            self::Settled => 'Đã quyết toán',
            self::Failed => 'Thất bại',
        };
    }
}
