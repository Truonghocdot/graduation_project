<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ServiceRequestSnapshotController extends Controller
{
    public function __invoke(
        ServiceRequest $serviceRequest,
        Request $request,
    ): ServiceRequestResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        abort_unless($serviceRequest->created_by === $user->id, 404);

        return new ServiceRequestResource($serviceRequest->load([
            'vehicleType',
            'quote',
            'stops',
            'deliveryOrder',
            'rideBooking',
            'payment.settlement',
            'assignments.driverProfile.user',
            'assignments.vehicle',
            'evidences',
        ]));
    }
}
