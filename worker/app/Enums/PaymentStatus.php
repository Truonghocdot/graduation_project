<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Ready = 'READY';
    case SettlementPending = 'SETTLEMENT_PENDING';
    case Settled = 'SETTLED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
    case Refunded = 'REFUNDED';
}
