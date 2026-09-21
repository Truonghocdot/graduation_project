<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case Scheduled = 'SCHEDULED';
    case SearchingDriver = 'SEARCHING_DRIVER';
    case Cancelled = 'CANCELLED';
    case Assigned = 'ASSIGNED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
}
