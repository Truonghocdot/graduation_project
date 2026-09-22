<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'OPEN';
    case InReview = 'IN_REVIEW';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';
}
