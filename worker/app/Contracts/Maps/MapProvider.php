<?php

namespace App\Contracts\Maps;

use App\Data\Maps\Coordinates;
use App\Data\Maps\RouteResult;

interface MapProvider
{
    public function route(
        Coordinates $origin,
        Coordinates $destination,
        string $vehicleTypeKey,
    ): RouteResult;
}
