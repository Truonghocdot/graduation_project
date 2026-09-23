<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MapRouteUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'Nhà cung cấp lộ trình hiện tạm thời không khả dụng.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
