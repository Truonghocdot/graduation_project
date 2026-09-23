<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceRequestStatus: string implements HasLabel
{
    case Scheduled = 'SCHEDULED';
    case SearchingDriver = 'SEARCHING_DRIVER';
    case Cancelled = 'CANCELLED';
    case Assigned = 'ASSIGNED';
    case DriverArrivingPickup = 'DRIVER_ARRIVING_PICKUP';
    case AtPickup = 'AT_PICKUP';
    case PickedUp = 'PICKED_UP';
    case InDelivery = 'IN_DELIVERY';
    case Delivered = 'DELIVERED';
    case DeliveryFailed = 'DELIVERY_FAILED';
    case Returning = 'RETURNING';
    case Returned = 'RETURNED';
    case DriverArriving = 'DRIVER_ARRIVING';
    case DriverArrived = 'DRIVER_ARRIVED';
    case InTrip = 'IN_TRIP';
    case TripEnded = 'TRIP_ENDED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Đã đặt lịch',
            self::SearchingDriver => 'Đang tìm tài xế',
            self::Cancelled => 'Đã hủy',
            self::Assigned => 'Đã phân công tài xế',
            self::DriverArrivingPickup => 'Tài xế đang đến điểm lấy hàng',
            self::AtPickup => 'Tài xế đã đến điểm lấy hàng',
            self::PickedUp => 'Đã lấy hàng',
            self::InDelivery => 'Đang giao hàng',
            self::Delivered => 'Đã giao hàng',
            self::DeliveryFailed => 'Giao hàng thất bại',
            self::Returning => 'Đang hoàn hàng',
            self::Returned => 'Đã hoàn hàng',
            self::DriverArriving => 'Tài xế đang đến',
            self::DriverArrived => 'Tài xế đã đến',
            self::InTrip => 'Đang trong chuyến đi',
            self::TripEnded => 'Đã kết thúc chuyến đi',
            self::InProgress => 'Đang thực hiện',
            self::Completed => 'Đã hoàn thành',
        };
    }
}
