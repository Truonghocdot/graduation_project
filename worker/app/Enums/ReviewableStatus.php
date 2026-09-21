<?php

namespace App\Enums;

enum ReviewableStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Suspended = 'SUSPENDED';
}
