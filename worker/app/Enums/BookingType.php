<?php

namespace App\Enums;

enum BookingType: string
{
    case Now = 'NOW';
    case Scheduled = 'SCHEDULED';
}
