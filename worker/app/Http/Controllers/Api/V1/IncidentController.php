<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\StoreIncidentRequest;
use App\Http\Resources\Api\V1\IncidentResource;
use App\Models\Incident;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Support\IncidentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IncidentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);

        return IncidentResource::collection(
            Incident::query()
                ->where('reported_by', $user->id)
                ->with('serviceRequest')
                ->latest()
                ->paginate(20),
        );
    }

    public function store(
        StoreIncidentRequest $request,
        ServiceRequest $serviceRequest,
        IncidentService $incidents,
    ): IncidentResource {
        $user = $request->user();
        assert($user instanceof User);

        return new IncidentResource($incidents->report(
            $user,
            $serviceRequest,
            $request->validated(),
            $request->idempotencyKey(),
        ));
    }
}
