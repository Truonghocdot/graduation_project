<?php

namespace App\Services\Maps;

use App\Contracts\Maps\MapProvider;
use App\Data\Maps\Coordinates;
use App\Data\Maps\RouteResult;
use App\Exceptions\MapRouteUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoongMapProvider implements MapProvider
{
    public function route(
        Coordinates $origin,
        Coordinates $destination,
        string $vehicleTypeKey,
    ): RouteResult {
        $apiKey = (string) config('services.goong.api_key');

        if ($apiKey === '') {
            throw new MapRouteUnavailableException('Chưa cấu hình khóa API Goong.');
        }

        $vehicle = $this->providerVehicle($vehicleTypeKey);
        $cacheKey = 'maps:goong:route:'.hash('sha256', implode('|', [
            $origin->toProviderString(),
            $destination->toProviderString(),
            $vehicle,
        ]));

        return Cache::remember(
            $cacheKey,
            (int) config('services.goong.cache_ttl_seconds', 300),
            fn (): RouteResult => $this->requestRoute($origin, $destination, $vehicle, $apiKey),
        );
    }

    private function requestRoute(
        Coordinates $origin,
        Coordinates $destination,
        string $vehicle,
        string $apiKey,
    ): RouteResult {
        try {
            $response = Http::baseUrl((string) config('services.goong.base_url'))
                ->acceptJson()
                ->connectTimeout((int) config('services.goong.connect_timeout_seconds', 3))
                ->timeout((int) config('services.goong.timeout_seconds', 8))
                ->retry(
                    (int) config('services.goong.retry_times', 2),
                    (int) config('services.goong.retry_delay_milliseconds', 200),
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->get('/Direction', [
                    'origin' => $origin->toProviderString(),
                    'destination' => $destination->toProviderString(),
                    'vehicle' => $vehicle,
                    'api_key' => $apiKey,
                ]);
        } catch (Throwable $exception) {
            throw new MapRouteUnavailableException(previous: $exception);
        }

        if (! $response->successful()) {
            throw new MapRouteUnavailableException(
                "Goong trả về lỗi HTTP {$response->status()}.",
            );
        }

        $route = $response->json('routes.0');
        $distance = data_get($route, 'legs.0.distance.value');
        $duration = data_get($route, 'legs.0.duration.value');

        if (! is_array($route) || ! is_numeric($distance) || ! is_numeric($duration)) {
            throw new MapRouteUnavailableException('Goong không trả về lộ trình có thể sử dụng.');
        }

        return new RouteResult(
            provider: 'goong',
            distanceMeters: (float) $distance,
            durationSeconds: (int) $duration,
            encodedPolyline: data_get($route, 'overview_polyline.points'),
            metadata: [
                'summary' => data_get($route, 'summary'),
                'warnings' => data_get($route, 'warnings', []),
            ],
        );
    }

    private function providerVehicle(string $vehicleTypeKey): string
    {
        $configuredVehicle = config("services.goong.vehicle_mapping.{$vehicleTypeKey}");

        return is_string($configuredVehicle) && $configuredVehicle !== ''
            ? $configuredVehicle
            : 'car';
    }
}
