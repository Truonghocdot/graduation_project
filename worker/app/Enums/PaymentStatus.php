<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Ready = 'READY';
    case SettlementPending = 'SETTLEMENT_PENDING';
    case Settled = 'SETTLED';
    case Cancelled = 'CANCELLED';
    case Refunded = 'REFUNDED';
}
