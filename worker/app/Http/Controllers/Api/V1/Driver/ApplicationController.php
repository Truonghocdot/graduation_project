<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StoreDriverApplicationRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Services\Driver\DriverOnboardingService;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function show(Request $request, DriverOnboardingService $onboarding): DriverProfileResource
    {
        return DriverProfileResource::make($onboarding->profileFor($request->user()));
    }

    public function store(
        StoreDriverApplicationRequest $request,
        DriverOnboardingService $onboarding,
    ): DriverProfileResource {
        return DriverProfileResource::make(
            $onboarding->saveDraft($request->user()),
        );
    }
}
