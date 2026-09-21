<?php

namespace App\Contracts\Realtime;

interface LocationPublisher
{
    /** @param array<string, mixed> $payload */
    public function publish(array $payload): void;
}
