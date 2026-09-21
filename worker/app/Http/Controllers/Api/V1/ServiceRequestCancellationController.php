<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\CancelServiceRequest;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Booking\ServiceRequestCancellationService;

class ServiceRequestCancellationController extends Controller
{
    public function __invoke(
        CancelServiceRequest $request,
        ServiceRequest $serviceRequest,
        ServiceRequestCancellationService $cancellationService,
    ): ServiceRequestResource {
        $user = $request->user();
        assert($user instanceof User);

        return new ServiceRequestResource($cancellationService->cancel(
            $user,
            $serviceRequest,
            $request->validated(),
            $request->idempotencyKey(),
        ));
    }
}
