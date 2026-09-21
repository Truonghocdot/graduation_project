<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\OfferActionRequest;
use App\Http\Resources\Api\V1\DriverOfferResource;
use App\Models\DriverOffer;
use App\Models\User;
use App\Services\Matching\OfferResponseService;

class OfferResponseController extends Controller
{
    public function __invoke(
        OfferActionRequest $request,
        DriverOffer $driverOffer,
        OfferResponseService $responseService,
    ): DriverOfferResource {
        $user = $request->user();
        assert($user instanceof User);

        return new DriverOfferResource(
            $responseService->respond(
                $user,
                $driverOffer,
                (string) $request->validated('action'),
                $request->idempotencyKey(),
            ),
        );
    }
}
