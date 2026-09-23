<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupportTicketStatus: string implements HasLabel
{
    case Open = 'OPEN';
    case InReview = 'IN_REVIEW';
    case WaitingForCustomer = 'WAITING_FOR_CUSTOMER';
    case Resolved = 'RESOLVED';
    case Reopened = 'REOPENED';
    case Closed = 'CLOSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::InReview => 'Đang xử lý',
            self::WaitingForCustomer => 'Chờ khách hàng phản hồi',
            self::Resolved => 'Đã xử lý',
            self::Reopened => 'Đã mở lại',
            self::Closed => 'Đã đóng',
        };
    }
}
