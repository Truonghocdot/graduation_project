<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\StoreRatingRequest;
use App\Http\Resources\Api\V1\RatingResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Support\RatingService;

class RatingController extends Controller
{
    public function store(
        StoreRatingRequest $request,
        ServiceRequest $serviceRequest,
        RatingService $ratings,
    ): RatingResource {
        $user = $request->user();
        assert($user instanceof User);

        return new RatingResource($ratings->submit(
            $user,
            $serviceRequest,
            $request->validated(),
        ));
    }
}
