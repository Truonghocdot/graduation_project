<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListDriverApplicationsRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\DriverProfile;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DriverApplicationController extends Controller
{
    public function index(ListDriverApplicationsRequest $request): AnonymousResourceCollection
    {
        $applications = DriverProfile::query()
            ->with(['user', 'vehicles.vehicleType'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('review_status', $request->string('status')->toString()),
            )
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return DriverProfileResource::collection($applications);
    }

    public function show(DriverProfile $driverApplication): DriverProfileResource
    {
        return DriverProfileResource::make($driverApplication->load([
            'user',
            'documents.vehicle',
            'vehicles.vehicleType',
            'vehicles.documents.vehicle',
            'capabilities.vehicleType',
            'lastLocation',
        ]));
    }
}
