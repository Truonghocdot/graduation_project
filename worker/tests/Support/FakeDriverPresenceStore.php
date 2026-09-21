<?php

namespace Tests\Support;

use App\Contracts\Matching\DriverPresenceStore;

class FakeDriverPresenceStore implements DriverPresenceStore
{
    /** @var array<int, array{driver_profile_id: int, distance_meters: float}> */
    public array $nearbyDrivers = [];

    /** @var array<int, array{services: array<int, string>, latitude: float, longitude: float, ttl: int}> */
    public array $online = [];

    /** @var array<int, int> */
    public array $offline = [];

    public function markOnline(
        int $driverProfileId,
        array $serviceTypes,
        float $latitude,
        float $longitude,
        int $ttlSeconds,
    ): void {
        $this->online[$driverProfileId] = [
            'services' => $serviceTypes,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'ttl' => $ttlSeconds,
        ];
    }

    public function markOffline(int $driverProfileId): void
    {
        $this->offline[] = $driverProfileId;
    }

    public function nearby(
        array $serviceTypes,
        float $latitude,
        float $longitude,
        float $radiusMeters,
        int $limit,
    ): array {
        return array_slice($this->nearbyDrivers, 0, $limit);
    }
}
