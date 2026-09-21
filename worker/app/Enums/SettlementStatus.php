<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Settled = 'SETTLED';
    case Failed = 'FAILED';
}
