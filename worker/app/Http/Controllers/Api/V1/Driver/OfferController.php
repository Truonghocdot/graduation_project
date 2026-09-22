<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\AssignmentStatus;
use App\Enums\DriverOfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DriverOfferResource;
use App\Models\DriverOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
                ->where(function (Builder $query): void {
                    $query->where('status', DriverOfferStatus::Pending->value)
                        ->orWhere(function (Builder $query): void {
                            $query->where('status', DriverOfferStatus::Accepted->value)
                                ->whereHas('assignment', fn ($query) => $query
                                    ->where('status', AssignmentStatus::Active->value));
                        });
                })
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
