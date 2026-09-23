<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IncidentStatus: string implements HasLabel
{
    case Open = 'OPEN';
    case InReview = 'IN_REVIEW';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::InReview => 'Đang xử lý',
            self::Resolved => 'Đã xử lý',
            self::Closed => 'Đã đóng',
        };
    }
}
