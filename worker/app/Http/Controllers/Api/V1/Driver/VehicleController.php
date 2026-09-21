<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StoreVehicleRequest;
use App\Http\Requests\Api\V1\Driver\UpdateVehicleRequest;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\Vehicle;
use App\Services\Driver\DriverOnboardingService;

class VehicleController extends Controller
{
    public function store(
        StoreVehicleRequest $request,
        DriverOnboardingService $onboarding,
    ): VehicleResource {
        return VehicleResource::make(
            $onboarding->addVehicle($request->user(), $request->vehicleAttributes()),
        );
    }

    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle,
        DriverOnboardingService $onboarding,
    ): VehicleResource {
        return VehicleResource::make(
            $onboarding->updateVehicle($request->user(), $vehicle, $request->vehicleAttributes()),
        );
    }
}
