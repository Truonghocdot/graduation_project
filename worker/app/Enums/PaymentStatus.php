<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel
{
    case Ready = 'READY';
    case SettlementPending = 'SETTLEMENT_PENDING';
    case Settled = 'SETTLED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
    case Refunded = 'REFUNDED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ready => 'Sẵn sàng thanh toán',
            self::SettlementPending => 'Chờ quyết toán',
            self::Settled => 'Đã quyết toán',
            self::Failed => 'Thất bại',
            self::Cancelled => 'Đã hủy',
            self::PartiallyRefunded => 'Đã hoàn tiền một phần',
            self::Refunded => 'Đã hoàn tiền',
        };
    }
}
