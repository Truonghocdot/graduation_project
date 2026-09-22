<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceRequestHistoryController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return ServiceRequestResource::collection(
            ServiceRequest::query()
                ->where('created_by', $user->id)
                ->with([
                    'vehicleType',
                    'quote',
                    'stops',
                    'deliveryOrder',
                    'rideBooking',
                    'payment.settlement',
                    'assignments.driverProfile.user',
                    'assignments.vehicle',
                    'evidences',
                ])
                ->latest('id')
                ->paginate(20),
        );
    }
}
