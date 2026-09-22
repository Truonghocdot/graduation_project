<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobHistoryController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return ServiceRequestResource::collection(
            ServiceRequest::query()
                ->whereHas('assignments', function ($query) use ($user): void {
                    $query->whereIn('status', [
                        AssignmentStatus::Completed->value,
                        AssignmentStatus::Cancelled->value,
                    ])->whereHas(
                        'driverProfile',
                        fn ($query) => $query->where('user_id', $user->id),
                    );
                })
                ->with([
                    'vehicleType',
                    'quote',
                    'stops',
                    'deliveryOrder',
                    'rideBooking',
                    'payment.settlement',
                    'assignments' => fn ($query) => $query
                        ->whereHas('driverProfile', fn ($query) => $query->where('user_id', $user->id))
                        ->latest('id'),
                    'assignments.driverProfile.user',
                    'assignments.vehicle',
                    'evidences',
                ])
                ->latest('id')
                ->paginate(20),
        );
    }
}
