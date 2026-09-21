<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\UpdateLocationRequest;
use App\Models\User;
use App\Services\Execution\DriverLocationService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __invoke(
        UpdateLocationRequest $request,
        DriverLocationService $locations,
    ): JsonResponse {
        $user = $request->user();
        assert($user instanceof User);
        $location = $locations->update($user, $request->validated());

        return response()->json([
            'data' => [
                'location' => $location->last_location,
                'captured_at' => $location->last_location_at,
            ],
        ]);
    }
}
