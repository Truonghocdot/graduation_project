<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DriverOfferResource;
use App\Models\DriverOffer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return DriverOfferResource::collection(
            DriverOffer::query()
                ->whereHas('driverProfile', fn ($query) => $query->where('user_id', $user->id))
                ->whereIn('status', ['PENDING', 'ACCEPTED'])
                ->with([
                    'serviceRequest.stops',
                    'serviceRequest.vehicleType',
                    'serviceRequest.payment',
                ])
                ->latest('offered_at')
                ->limit(50)
                ->get(),
        );
    }
}
