<?php

namespace App\Enums;

enum ServiceRequestStatus: string
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
}
