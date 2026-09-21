<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Execution\TransitionServiceRequest;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Execution\ServiceExecutionService;

class ServiceExecutionController extends Controller
{
    public function update(
        TransitionServiceRequest $request,
        ServiceRequest $serviceRequest,
        ServiceExecutionService $execution,
    ): ServiceRequestResource {
        $user = $request->user();
        assert($user instanceof User);

        return new ServiceRequestResource($execution->transition(
            $user,
            $serviceRequest,
            $request->validated(),
            $request->idempotencyKey(),
        ));
    }
}
