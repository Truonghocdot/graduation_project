<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SuspendDriverRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\DriverProfile;
use App\Services\Driver\DriverReviewService;

class SuspendedDriverController extends Controller
{
    public function __invoke(
        SuspendDriverRequest $request,
        DriverProfile $driver,
        DriverReviewService $review,
    ): DriverProfileResource {
        return DriverProfileResource::make($review->suspend(
            $driver,
            $request->user(),
            $request->string('reason_code')->toString(),
        ));
    }
}
