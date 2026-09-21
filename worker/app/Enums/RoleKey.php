<?php

namespace App\Enums;

enum RoleKey: string
{
    case Customer = 'CUSTOMER';
    case Driver = 'DRIVER';
    case Support = 'SUPPORT';
    case Admin = 'ADMIN';
}
