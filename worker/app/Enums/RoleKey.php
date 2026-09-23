<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoleKey: string implements HasLabel
{
    case Customer = 'CUSTOMER';
    case Driver = 'DRIVER';
    case Support = 'SUPPORT';
    case Admin = 'ADMIN';

    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => 'Khách hàng',
            self::Driver => 'Tài xế',
            self::Support => 'Hỗ trợ',
            self::Admin => 'Quản trị viên',
        };
    }
}
