<?php

namespace App\Enums;

enum DriverOfferStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';
}
