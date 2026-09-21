<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\Vehicle;
use App\Services\Driver\DriverOnboardingService;
use Illuminate\Http\Request;

class SelectedVehicleController extends Controller
{
    public function __invoke(
        Request $request,
        Vehicle $vehicle,
        DriverOnboardingService $onboarding,
    ): VehicleResource {
        return VehicleResource::make($onboarding->selectVehicle($request->user(), $vehicle));
    }
}
