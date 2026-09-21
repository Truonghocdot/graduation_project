<?php

namespace App\Enums;

enum DriverAvailabilityStatus: string
{
    case Offline = 'OFFLINE';
    case Online = 'ONLINE';
    case Offered = 'OFFERED';
    case Busy = 'BUSY';
}
