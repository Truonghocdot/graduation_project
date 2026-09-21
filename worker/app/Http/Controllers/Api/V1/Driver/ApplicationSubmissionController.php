<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\SubmitDriverApplicationRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Services\Driver\DriverOnboardingService;

class ApplicationSubmissionController extends Controller
{
    public function __invoke(
        SubmitDriverApplicationRequest $request,
        DriverOnboardingService $onboarding,
    ): DriverProfileResource {
        return DriverProfileResource::make($onboarding->submit(
            $request->user(),
            $request->string('vehicle_id')->toString(),
            $request->array('service_types'),
        ));
    }
}
