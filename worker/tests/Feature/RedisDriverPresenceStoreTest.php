<?php

use App\Services\Matching\RedisDriverPresenceStore;
use Illuminate\Support\Facades\Redis;

test('searches nearby drivers with a raw GeoSearch command', function () {
    config()->set('database.redis.options.prefix', 'drive-database-');

    $connection = Mockery::mock();
    $connection->shouldReceive('executeRaw')
        ->once()
        ->with([
            'GEOSEARCH',
            'drive-database-drivers:geo',
            'FROMLONLAT',
            105.8204983,
            21.0393984,
            'BYRADIUS',
            5000.0,
            'm',
            'ASC',
            'COUNT',
            5,
            'WITHDIST',
        ])
        ->andReturn([['13', '86.9431']]);

    Redis::shouldReceive('connection')->once()->andReturn($connection);
    Redis::shouldReceive('sismember')
        ->once()
        ->with('driver:services:13', 'DRIVE')
        ->andReturn(1);

    expect((new RedisDriverPresenceStore)->nearby(
        ['DRIVE'],
        21.0393984,
        105.8204983,
        5000.0,
        5,
    ))->toBe([
        ['driver_profile_id' => 13, 'distance_meters' => 86.9431],
    ]);
});
