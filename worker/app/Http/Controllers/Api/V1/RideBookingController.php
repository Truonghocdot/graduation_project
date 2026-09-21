<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\StoreRideBookingRequest;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\User;
use App\Services\Booking\ServiceRequestService;

class RideBookingController extends Controller
{
    public function store(
        StoreRideBookingRequest $request,
        ServiceRequestService $serviceRequestService,
    ): ServiceRequestResource {
        $user = $request->user();
        assert($user instanceof User);

        return new ServiceRequestResource($serviceRequestService->createRide(
            $user,
            $request->validated(),
            $request->idempotencyKey(),
        ));
    }
}
