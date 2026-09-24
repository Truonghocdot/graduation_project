<?php

namespace App\Support;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;

final class ServiceStatusMetadata
{
    /** @return array{code: string, label: string, phase: string, progress_index: int, progress_total: int, terminal: bool, can_cancel: bool} */
    public static function for(ServiceType $serviceType, ServiceRequestStatus $status): array
    {
        $delivery = [
            'SCHEDULED' => ['SCHEDULED', 0, 6],
            'SEARCHING_DRIVER' => ['SEARCHING', 1, 6],
            'ASSIGNED' => ['ASSIGNED', 2, 6],
            'DRIVER_ARRIVING_PICKUP' => ['EN_ROUTE', 2, 6],
            'AT_PICKUP' => ['PICKUP', 3, 6],
            'PICKED_UP' => ['PICKUP', 3, 6],
            'IN_DELIVERY' => ['IN_TRANSIT', 4, 6],
            'DELIVERED' => ['COMPLETED', 5, 6],
            'COMPLETED' => ['COMPLETED', 6, 6],
            'DELIVERY_FAILED' => ['FAILED', 5, 6],
            'RETURNING' => ['RETURNING', 5, 6],
            'RETURNED' => ['COMPLETED', 6, 6],
            'CANCELLED' => ['CANCELLED', 0, 6],
        ];
        $ride = [
            'SCHEDULED' => ['SCHEDULED', 0, 5],
            'SEARCHING_DRIVER' => ['SEARCHING', 1, 5],
            'ASSIGNED' => ['ASSIGNED', 2, 5],
            'DRIVER_ARRIVING' => ['EN_ROUTE', 2, 5],
            'DRIVER_ARRIVED' => ['PICKUP', 3, 5],
            'IN_TRIP' => ['IN_TRANSIT', 4, 5],
            'TRIP_ENDED' => ['COMPLETED', 5, 5],
            'COMPLETED' => ['COMPLETED', 5, 5],
            'CANCELLED' => ['CANCELLED', 0, 5],
        ];
        $definition = ($serviceType === ServiceType::Delivery ? $delivery : $ride)[$status->value]
            ?? ['UNKNOWN', 0, $serviceType === ServiceType::Delivery ? 6 : 5];

        return [
            'code' => $status->value,
            'label' => $status->getLabel(),
            'phase' => $definition[0],
            'progress_index' => $definition[1],
            'progress_total' => $definition[2],
            'terminal' => in_array($status, [
                ServiceRequestStatus::Cancelled,
                ServiceRequestStatus::Completed,
                ServiceRequestStatus::Delivered,
                ServiceRequestStatus::Returned,
                ServiceRequestStatus::TripEnded,
                ServiceRequestStatus::DeliveryFailed,
            ], true),
            'can_cancel' => in_array($status, [
                ServiceRequestStatus::Scheduled,
                ServiceRequestStatus::SearchingDriver,
            ], true),
        ];
    }
}
