<?php

namespace App\Enums;

enum SupportPriority: string
{
    case Low = 'LOW';
    case Normal = 'NORMAL';
    case High = 'HIGH';
    case Urgent = 'URGENT';
}
