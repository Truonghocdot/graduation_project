<?php

namespace App\Services\Execution;

use App\Contracts\Realtime\LocationPublisher;

class NullLocationPublisher implements LocationPublisher
{
    public function publish(array $payload): void {}
}
