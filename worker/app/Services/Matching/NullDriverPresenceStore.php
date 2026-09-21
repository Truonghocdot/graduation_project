<?php

namespace App\Services\Matching;

use App\Contracts\Matching\DriverPresenceStore;

class NullDriverPresenceStore implements DriverPresenceStore
{
    public function markOnline(
        int $driverProfileId,
        array $serviceTypes,
        float $latitude,
        float $longitude,
        int $ttlSeconds,
    ): void {}

    public function markOffline(int $driverProfileId): void {}

    public function nearby(
        array $serviceTypes,
        float $latitude,
        float $longitude,
        float $radiusMeters,
        int $limit,
    ): array {
        return [];
    }
}
