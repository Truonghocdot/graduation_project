<?php

namespace App\Services\Execution;

use App\Enums\AssignmentStatus;
use App\Enums\EvidenceType;
use App\Models\Assignment;
use App\Models\ServiceEvidence;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ServiceEvidenceService
{
    /** @param array<string, mixed> $metadata */
    public function store(
        User $driver,
        ServiceRequest $serviceRequest,
        EvidenceType $type,
        UploadedFile $file,
        array $metadata,
    ): ServiceEvidence {
        $assignment = $this->activeAssignment($driver, $serviceRequest);
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store(
            'service-evidence/'.$serviceRequest->public_id.'/'.$type->value,
            'local',
        );

        if ($path === false) {
            throw new \RuntimeException('The evidence file could not be stored.');
        }

        try {
            return ServiceEvidence::query()->create([
                'service_request_id' => $serviceRequest->id,
                'assignment_id' => $assignment->id,
                'uploaded_by' => $driver->id,
                'evidence_type' => $type,
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'sha256' => $hash,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function activeAssignment(User $driver, ServiceRequest $request): Assignment
    {
        return Assignment::query()
            ->where('service_request_id', $request->id)
            ->where('status', AssignmentStatus::Active->value)
            ->whereHas('driverProfile', fn ($query) => $query->where('user_id', $driver->id))
            ->firstOrFail();
    }
}
