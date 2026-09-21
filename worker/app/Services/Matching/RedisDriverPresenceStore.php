<?php

namespace App\Services\Matching;

use App\Contracts\Matching\DriverPresenceStore;
use Illuminate\Support\Facades\Redis;

class RedisDriverPresenceStore implements DriverPresenceStore
{
    private const GEO_KEY = 'drivers:geo';

    private const PRESENCE_PREFIX = 'driver:presence:';

    private const SERVICES_PREFIX = 'driver:services:';

    public function markOnline(
        int $driverProfileId,
        array $serviceTypes,
        float $latitude,
        float $longitude,
        int $ttlSeconds,
    ): void {
        Redis::command('GEOADD', [self::GEO_KEY, $longitude, $latitude, $driverProfileId]);
        Redis::setex(
            self::PRESENCE_PREFIX.$driverProfileId,
            $ttlSeconds,
            json_encode([
                'driver_profile_id' => $driverProfileId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'service_types' => $serviceTypes,
            ], JSON_THROW_ON_ERROR),
        );
        Redis::del(self::SERVICES_PREFIX.$driverProfileId);
        foreach ($serviceTypes as $serviceType) {
            Redis::sadd(self::SERVICES_PREFIX.$driverProfileId, $serviceType);
        }
        Redis::expire(self::SERVICES_PREFIX.$driverProfileId, $ttlSeconds);
    }

    public function markOffline(int $driverProfileId): void
    {
        Redis::zrem(self::GEO_KEY, (string) $driverProfileId);
        Redis::del(self::PRESENCE_PREFIX.$driverProfileId, self::SERVICES_PREFIX.$driverProfileId);
    }

    public function nearby(
        array $serviceTypes,
        float $latitude,
        float $longitude,
        float $radiusMeters,
        int $limit,
    ): array {
        $rows = Redis::command('GEOSEARCH', [
            self::GEO_KEY,
            'FROMLONLAT',
            $longitude,
            $latitude,
            'BYRADIUS',
            $radiusMeters,
            'm',
            'ASC',
            'COUNT',
            $limit,
            'WITHDIST',
        ]);
        $matches = [];

        foreach ($rows as $row) {
            $profileId = (int) (is_array($row) ? ($row[0] ?? 0) : $row);
            $distance = (float) (is_array($row) ? ($row[1] ?? 0) : 0);

            if ($profileId <= 0 || ! $this->supportsAnyService($profileId, $serviceTypes)) {
                continue;
            }

            $matches[] = [
                'driver_profile_id' => $profileId,
                'distance_meters' => $distance,
            ];
        }

        return $matches;
    }

    /** @param array<int, string> $serviceTypes */
    private function supportsAnyService(int $driverProfileId, array $serviceTypes): bool
    {
        foreach ($serviceTypes as $serviceType) {
            if ((bool) Redis::sismember(self::SERVICES_PREFIX.$driverProfileId, $serviceType)) {
                return true;
            }
        }

        return false;
    }
}
