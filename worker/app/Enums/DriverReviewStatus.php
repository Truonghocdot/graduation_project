<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DriverReviewStatus: string implements HasLabel
{
    case Draft = 'DRAFT';
    case PendingReview = 'PENDING_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Suspended = 'SUSPENDED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::PendingReview => 'Chờ xét duyệt',
            self::Approved => 'Đã phê duyệt',
            self::Rejected => 'Bị từ chối',
            self::Suspended => 'Tạm ngưng',
        };
    }
}
