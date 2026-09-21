<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MapRouteUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'The route provider is temporarily unavailable.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
