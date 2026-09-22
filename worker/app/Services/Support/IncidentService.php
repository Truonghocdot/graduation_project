<?php

namespace App\Services\Support;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\OutboxEvent;
use App\Models\ServiceEvidence;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Booking\IdempotencyService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** @param array<string, mixed> $data */
    public function report(
        User $user,
        ServiceRequest $serviceRequest,
        array $data,
        string $idempotencyKey,
    ): Incident {
        return DB::transaction(function () use (
            $user,
            $serviceRequest,
            $data,
            $idempotencyKey,
        ): Incident {
            $idempotency = $this->idempotency->begin(
                $user,
                'incident.create',
                $idempotencyKey,
                ['service_request_id' => $serviceRequest->public_id, ...$data],
            );

            if ($idempotency->status === 'COMPLETED') {
                $replayed = Incident::query()->findOrFail($idempotency->resource_id);
                $replayed->wasRecentlyCreated = true;

                return $replayed->load(['reporter', 'serviceRequest']);
            }

            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if (! $this->participant($user, $request)) {
                abort(404);
            }

            $evidenceIds = $data['evidence_ids'] ?? [];
            $evidenceIds = is_array($evidenceIds) ? array_values($evidenceIds) : [];
            $evidences = ServiceEvidence::query()
                ->where('service_request_id', $request->id)
                ->whereIn('public_id', $evidenceIds)
                ->get();

            if ($evidences->count() !== count($evidenceIds)) {
                abort(404);
            }

            $incident = Incident::query()->create([
                'service_request_id' => $request->id,
                'reported_by' => $user->id,
                'incident_type' => $data['incident_type'],
                'severity' => $data['incident_type'] === 'SOS' ? 'CRITICAL' : 'HIGH',
                'status' => IncidentStatus::Open,
                'description' => $data['description'] ?? null,
                'evidence' => [
                    'service_evidence_ids' => $evidences->pluck('public_id')->all(),
                    'location' => array_filter([
                        'latitude' => $data['latitude'] ?? null,
                        'longitude' => $data['longitude'] ?? null,
                    ], fn (mixed $value): bool => $value !== null),
                ],
            ]);
            OutboxEvent::query()->create([
                'event_type' => $data['incident_type'] === 'SOS'
                    ? 'SAFETY_INCIDENT_REPORTED'
                    : 'INCIDENT_REPORTED',
                'aggregate_type' => 'SERVICE_REQUEST',
                'aggregate_id' => $request->id,
                'aggregate_version' => $request->version,
                'payload' => [
                    'service_request_id' => $request->public_id,
                    'incident_id' => $incident->public_id,
                    'incident_type' => $incident->incident_type,
                    'severity' => $incident->severity,
                ],
                'status' => 'PENDING',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);
            $this->notifications->create(
                $user,
                'INCIDENT_REPORTED',
                [
                    'service_request_id' => $request->public_id,
                    'incident_id' => $incident->public_id,
                    'status' => $incident->status->value,
                ],
                $request->id,
                $request->version,
            );
            $this->idempotency->complete(
                $idempotency,
                201,
                ['id' => $incident->public_id],
                Incident::class,
                $incident->id,
            );

            return $incident->load(['reporter', 'serviceRequest']);
        });
    }

    private function participant(User $user, ServiceRequest $request): bool
    {
        return $request->created_by === $user->id
            || $request->assignments()
                ->whereHas('driverProfile', fn ($query) => $query->where('user_id', $user->id))
                ->exists();
    }
}
