<?php

namespace App\Enums;

enum EvidenceType: string
{
    case Pickup = 'PICKUP';
    case Delivery = 'DELIVERY';
    case PassengerConfirmation = 'PASSENGER_CONFIRMATION';
    case Incident = 'INCIDENT';
}
