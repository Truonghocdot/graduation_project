<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\GoOnlineRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Services\Driver\DriverAvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function online(
        GoOnlineRequest $request,
        DriverAvailabilityService $availability,
    ): DriverProfileResource {
        return DriverProfileResource::make(
            $availability->goOnline($request->user(), $request->availabilityAttributes()),
        );
    }

    public function offline(
        Request $request,
        DriverAvailabilityService $availability,
    ): DriverProfileResource {
        return DriverProfileResource::make($availability->goOffline($request->user()));
    }
}
