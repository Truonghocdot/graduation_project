<?php

namespace App\Services\Execution;

use App\Contracts\Realtime\LocationPublisher;
use Illuminate\Support\Facades\Redis;

class RedisLocationPublisher implements LocationPublisher
{
    public function publish(array $payload): void
    {
        Redis::publish(
            (string) config('matching.location_channel', 'worker.location'),
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
