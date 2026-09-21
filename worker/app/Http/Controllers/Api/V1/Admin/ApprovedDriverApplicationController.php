<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\DriverProfile;
use App\Services\Driver\DriverReviewService;
use Illuminate\Http\Request;

class ApprovedDriverApplicationController extends Controller
{
    public function __invoke(
        Request $request,
        DriverProfile $driverApplication,
        DriverReviewService $review,
    ): DriverProfileResource {
        return DriverProfileResource::make(
            $review->approve($driverApplication, $request->user()),
        );
    }
}
