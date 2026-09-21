<?php

use App\Data\Maps\Coordinates;
use App\Exceptions\MapRouteUnavailableException;
use App\Services\Maps\GoongMapProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('maps and caches a Goong direction response', function () {
    config()->set([
        'services.goong.base_url' => 'https://rsapi.goong.io',
        'services.goong.api_key' => 'test-key',
        'services.goong.cache_ttl_seconds' => 300,
        'services.goong.vehicle_mapping.MOTORBIKE' => 'bike',
    ]);
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'https://rsapi.goong.io/Direction*' => Http::response([
            'routes' => [[
                'summary' => 'Goong route',
                'overview_polyline' => ['points' => 'polyline'],
                'legs' => [[
                    'distance' => ['value' => 4_200],
                    'duration' => ['value' => 900],
                ]],
            ]],
        ]),
    ]);
    $provider = app(GoongMapProvider::class);
    $origin = new Coordinates(10.773, 106.704);
    $destination = new Coordinates(10.780, 106.690);

    $first = $provider->route($origin, $destination, 'MOTORBIKE');
    $second = $provider->route($origin, $destination, 'MOTORBIKE');

    expect($first->distanceMeters)->toBe(4_200.0)
        ->and($first->durationSeconds)->toBe(900)
        ->and($first->encodedPolyline)->toBe('polyline')
        ->and($second)->toEqual($first);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['vehicle'] === 'bike'
        && $request['origin'] === '10.773000,106.704000'
        && $request['api_key'] === 'test-key');
});

test('rejects a Goong response without a usable route', function () {
    config()->set([
        'services.goong.base_url' => 'https://rsapi.goong.io',
        'services.goong.api_key' => 'test-key',
    ]);
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'https://rsapi.goong.io/Direction*' => Http::response(['routes' => []]),
    ]);

    expect(fn () => app(GoongMapProvider::class)->route(
        new Coordinates(10.773, 106.704),
        new Coordinates(10.780, 106.690),
        'CAR_4_SEAT',
    ))->toThrow(MapRouteUnavailableException::class, 'usable route');
});
