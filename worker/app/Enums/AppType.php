<?php

namespace App\Enums;

enum AppType: string
{
    case Customer = 'CUSTOMER_APP';
    case Driver = 'DRIVER_APP';

    /**
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Customer => ['customer:*'],
            self::Driver => ['driver:*'],
        };
    }
}
