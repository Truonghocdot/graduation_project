<?php

namespace App\Enums;

enum DriverReviewStatus: string
{
    case Draft = 'DRAFT';
    case PendingReview = 'PENDING_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Suspended = 'SUSPENDED';
}
