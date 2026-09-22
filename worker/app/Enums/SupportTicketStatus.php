<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'OPEN';
    case InReview = 'IN_REVIEW';
    case WaitingForCustomer = 'WAITING_FOR_CUSTOMER';
    case Resolved = 'RESOLVED';
    case Reopened = 'REOPENED';
    case Closed = 'CLOSED';
}
