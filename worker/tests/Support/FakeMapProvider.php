<?php

namespace Tests\Support;

use App\Contracts\Maps\MapProvider;
use App\Data\Maps\Coordinates;
use App\Data\Maps\RouteResult;
use App\Exceptions\MapRouteUnavailableException;

class FakeMapProvider implements MapProvider
{
    /** @var array<int, array{origin: Coordinates, destination: Coordinates, vehicle_type_key: string}> */
    public array $calls = [];

    public function __construct(
        private readonly ?RouteResult $result = null,
        private readonly bool $fails = false,
    ) {}

    public function route(
        Coordinates $origin,
        Coordinates $destination,
        string $vehicleTypeKey,
    ): RouteResult {
        $this->calls[] = [
            'origin' => $origin,
            'destination' => $destination,
            'vehicle_type_key' => $vehicleTypeKey,
        ];

        if ($this->fails || $this->result === null) {
            throw new MapRouteUnavailableException('Fake route provider unavailable.');
        }

        return $this->result;
    }
}
