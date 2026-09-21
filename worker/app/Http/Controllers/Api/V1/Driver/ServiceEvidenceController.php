<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\EvidenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Execution\StoreEvidenceRequest;
use App\Http\Resources\Api\V1\ServiceEvidenceResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Execution\ServiceEvidenceService;

class ServiceEvidenceController extends Controller
{
    public function store(
        StoreEvidenceRequest $request,
        ServiceRequest $serviceRequest,
        ServiceEvidenceService $evidenceService,
    ): ServiceEvidenceResource {
        $user = $request->user();
        assert($user instanceof User);
        $file = $request->file('file');
        assert($file !== null);

        return new ServiceEvidenceResource($evidenceService->store(
            $user,
            $serviceRequest,
            EvidenceType::from($request->string('evidence_type')->toString()),
            $file,
            $request->safe()->only(['latitude', 'longitude', 'note']),
        ));
    }
}
