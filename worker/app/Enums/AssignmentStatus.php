<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Active = 'ACTIVE';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
