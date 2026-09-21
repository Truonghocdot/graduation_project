<?php

namespace App\Contracts\Matching;

interface DriverPresenceStore
{
    /** @param array<int, string> $serviceTypes */
    public function markOnline(
        int $driverProfileId,
        array $serviceTypes,
        float $latitude,
        float $longitude,
        int $ttlSeconds,
    ): void;

    public function markOffline(int $driverProfileId): void;

    /**
     * @param  array<int, string>  $serviceTypes
     * @return array<int, array{driver_profile_id: int, distance_meters: float}>
     */
    public function nearby(
        array $serviceTypes,
        float $latitude,
        float $longitude,
        float $radiusMeters,
        int $limit,
    ): array;
}
