<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percent = 'PERCENT';
    case Fixed = 'FIXED';
}
