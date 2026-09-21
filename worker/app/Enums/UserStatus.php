<?php

namespace App\Enums;

enum UserStatus: string
{
    case PendingVerification = 'PENDING_VERIFICATION';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Closed = 'CLOSED';
}
