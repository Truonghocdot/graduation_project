<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssignmentStatus;
use App\Enums\RoleKey;
use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\ServiceStatusMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestTrackingController extends Controller
{
    public function __invoke(ServiceRequest $serviceRequest, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $assignment = $serviceRequest->assignments()
            ->where('status', AssignmentStatus::Active->value)
            ->with(['driverProfile.user', 'driverProfile.lastLocation', 'vehicle.vehicleType'])
            ->first();
        $isCustomer = $serviceRequest->created_by === $user->id;
        $isAssignedDriver = $assignment?->driverProfile?->user_id === $user->id;
        abort_unless($isCustomer || $isAssignedDriver || $user->hasRole(RoleKey::Admin), 404);

        $location = $assignment?->driverProfile?->lastLocation;
        $staleAfter = (int) config('matching.presence_ttl_seconds', 15);
        $isStale = $assignment !== null
            && ($location === null || $location->last_location_at->lt(now()->subSeconds($staleAfter)));

        return response()->json([
            'data' => [
                'id' => $serviceRequest->public_id,
                'status' => $serviceRequest->status->value,
                'status_meta' => ServiceStatusMetadata::for($serviceRequest->service_type, $serviceRequest->status),
                'stops' => $serviceRequest->stops()->get([
                    'stop_type', 'address', 'latitude', 'longitude',
                ])->map(fn ($stop): array => [
                    'type' => $stop->stop_type->value,
                    'address' => $stop->address,
                    'latitude' => (float) $stop->latitude,
                    'longitude' => (float) $stop->longitude,
                ])->values(),
                'driver' => $assignment === null ? null : [
                    'id' => $assignment->driverProfile->public_id,
                    'name' => $assignment->driverProfile->user->name,
                    'vehicle' => $assignment->vehicle === null ? null : [
                        'id' => $assignment->vehicle->public_id,
                        'plate_number' => $assignment->vehicle->plate_number,
                        'type' => $assignment->vehicle->vehicleType?->name,
                    ],
                ],
                'live_location' => $location === null ? null : [
                    'latitude' => (float) data_get($location->last_location, 'lat'),
                    'longitude' => (float) data_get($location->last_location, 'lng'),
                    'accuracy' => data_get($location->last_location, 'accuracy'),
                    'heading' => data_get($location->last_location, 'heading'),
                    'speed' => data_get($location->last_location, 'speed'),
                    'captured_at' => $location->last_location_at,
                ],
                'location_stale' => $isStale,
                'updated_at' => now(),
            ],
        ]);
    }
}
