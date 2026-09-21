<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Wallet = 'WALLET';
    case Cash = 'CASH';
}
