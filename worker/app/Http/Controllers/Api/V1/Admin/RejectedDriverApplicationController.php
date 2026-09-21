<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RejectDriverApplicationRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\DriverProfile;
use App\Services\Driver\DriverReviewService;

class RejectedDriverApplicationController extends Controller
{
    public function __invoke(
        RejectDriverApplicationRequest $request,
        DriverProfile $driverApplication,
        DriverReviewService $review,
    ): DriverProfileResource {
        return DriverProfileResource::make($review->reject(
            $driverApplication,
            $request->user(),
            $request->string('reason_code')->toString(),
        ));
    }
}
