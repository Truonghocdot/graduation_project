<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Active = 'ACTIVE';
    case Used = 'USED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';
}
