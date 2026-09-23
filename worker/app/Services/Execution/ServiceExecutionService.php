<?php

namespace App\Services\Execution;

use App\Enums\EvidenceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\Assignment;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\ServiceEvidence;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use App\Models\User;
use App\Services\Booking\IdempotencyService;
use App\Services\Finance\CodService;
use App\Services\Finance\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceExecutionService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly ServiceEvidenceService $evidenceService,
        private readonly CodService $cod,
        private readonly SettlementService $settlement,
    ) {}

    /** @param array<string, mixed> $data */
    public function transition(
        User $driver,
        ServiceRequest $serviceRequest,
        array $data,
        string $idempotencyKey,
    ): ServiceRequest {
        $action = (string) $data['action'];
        $terminalAction = in_array($action, ['deliver', 'complete'], true);
        $result = DB::transaction(function () use (
            $driver,
            $serviceRequest,
            $data,
            $idempotencyKey,
            $action,
        ): ServiceRequest {
            $idempotency = $this->idempotency->begin(
                $driver,
                'service-execution.transition',
                $idempotencyKey,
                ['service_request_id' => $serviceRequest->public_id, ...$data],
            );

            if ($idempotency->status === 'COMPLETED') {
                return $this->load(ServiceRequest::query()->findOrFail($idempotency->resource_id));
            }

            $request = ServiceRequest::query()
                ->with(['deliveryOrder', 'rideBooking'])
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);
            $assignment = $this->evidenceService->activeAssignment($driver, $request);
            $transition = $this->transitionDefinition($request->service_type, $action);

            if ($request->status !== $transition['from']) {
                throw ValidationException::withMessages([
                    'action' => [
                        "Action {$action} requires status {$transition['from']->value}.",
                    ],
                ]);
            }

            $evidence = $this->evidenceFor(
                $request,
                $assignment,
                $driver,
                $data['evidence_id'] ?? null,
                $this->requiredEvidence($request, $action, $transition['evidence']),
            );
            $distance = $this->assertGeofence(
                $request,
                $transition['stop'],
                (float) $data['latitude'],
                (float) $data['longitude'],
                $data['out_of_geofence_reason'] ?? null,
            );

            if ($action === 'pickup' && $request->deliveryOrder !== null && $evidence !== null) {
                $this->cod->advance(
                    $request->deliveryOrder,
                    $assignment,
                    $driver,
                    $evidence,
                    $idempotencyKey,
                );
            }

            if ($action === 'deliver' && $request->deliveryOrder !== null && $evidence !== null) {
                $this->cod->collect(
                    $request->deliveryOrder,
                    $assignment,
                    $driver,
                    $evidence,
                    (float) ($data['cod_collected'] ?? 0),
                    $idempotencyKey,
                );
            }

            if ($action === 'start' && $request->rideBooking !== null) {
                $request->rideBooking->forceFill(['started_at' => now()])->save();
            }

            if ($action === 'complete' && $request->rideBooking !== null) {
                $request->rideBooking->forceFill(['ended_at' => now()])->save();
            }

            if ($terminalAction = in_array($action, ['deliver', 'complete'], true)) {
                $this->preparePaymentForSettlement($request, $driver, $data);
            }

            $fromStatus = $request->status;
            $request->forceFill([
                'status' => $transition['to'],
                'version' => $request->version + 1,
            ])->save();
            ServiceStatusHistory::query()->create([
                'service_request_id' => $request->id,
                'version' => $request->version,
                'from_status' => $fromStatus->value,
                'to_status' => $transition['to']->value,
                'actor_user_id' => $driver->id,
                'actor_type' => 'DRIVER',
                'reason_code' => $data['out_of_geofence_reason'] ?? null,
                'metadata' => [
                    'distance_to_stop_meters' => $distance,
                    'evidence_id' => $evidence?->public_id,
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
            OutboxEvent::query()->create([
                'event_type' => $transition['event'],
                'aggregate_type' => 'SERVICE_REQUEST',
                'aggregate_id' => $request->id,
                'aggregate_version' => $request->version,
                'payload' => [
                    'service_request_id' => $request->public_id,
                    'status' => $transition['to']->value,
                    'evidence_id' => $evidence?->public_id,
                ],
                'status' => 'PENDING',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);
            $this->idempotency->complete(
                $idempotency,
                200,
                ['id' => $request->public_id, 'status' => $transition['to']->value],
                ServiceRequest::class,
                $request->id,
            );

            return $this->load($request);
        });

        if ($terminalAction) {
            $this->settlement->settle($result);
        }

        return $this->load($result->fresh());
    }

    /**
     * @return array{from: ServiceRequestStatus, to: ServiceRequestStatus, event: string, stop: string|null, evidence: EvidenceType|null}
     */
    private function transitionDefinition(ServiceType $serviceType, string $action): array
    {
        $definitions = $serviceType === ServiceType::Delivery
            ? [
                'arrive_pickup' => [
                    ServiceRequestStatus::DriverArrivingPickup,
                    ServiceRequestStatus::AtPickup,
                    'DELIVERY_DRIVER_AT_PICKUP',
                    'PICKUP',
                    null,
                ],
                'pickup' => [
                    ServiceRequestStatus::AtPickup,
                    ServiceRequestStatus::PickedUp,
                    'DELIVERY_PICKED_UP',
                    'PICKUP',
                    EvidenceType::Pickup,
                ],
                'start_delivery' => [
                    ServiceRequestStatus::PickedUp,
                    ServiceRequestStatus::InDelivery,
                    'DELIVERY_IN_TRANSIT',
                    null,
                    null,
                ],
                'deliver' => [
                    ServiceRequestStatus::InDelivery,
                    ServiceRequestStatus::Delivered,
                    'DELIVERY_DELIVERED',
                    'DROPOFF',
                    EvidenceType::Delivery,
                ],
            ]
            : [
                'arrive' => [
                    ServiceRequestStatus::DriverArriving,
                    ServiceRequestStatus::DriverArrived,
                    'RIDE_DRIVER_ARRIVED',
                    'PICKUP',
                    null,
                ],
                'start' => [
                    ServiceRequestStatus::DriverArrived,
                    ServiceRequestStatus::InTrip,
                    'RIDE_STARTED',
                    'PICKUP',
                    null,
                ],
                'complete' => [
                    ServiceRequestStatus::InTrip,
                    ServiceRequestStatus::TripEnded,
                    'RIDE_ENDED',
                    'DROPOFF',
                    null,
                ],
            ];

        if (! isset($definitions[$action])) {
            throw ValidationException::withMessages([
                'action' => ['Thao tác không hợp lệ với loại dịch vụ này.'],
            ]);
        }

        [$from, $to, $event, $stop, $evidence] = $definitions[$action];

        return compact('from', 'to', 'event', 'stop', 'evidence');
    }

    private function evidenceFor(
        ServiceRequest $request,
        Assignment $assignment,
        User $driver,
        mixed $evidenceId,
        ?EvidenceType $requiredType,
    ): ?ServiceEvidence {
        if ($requiredType === null && $evidenceId === null) {
            return null;
        }

        $evidence = ServiceEvidence::query()
            ->where('public_id', $evidenceId)
            ->where('service_request_id', $request->id)
            ->where('assignment_id', $assignment->id)
            ->where('uploaded_by', $driver->id)
            ->first();

        if ($evidence === null || ($requiredType !== null && $evidence->evidence_type !== $requiredType)) {
            throw ValidationException::withMessages([
                'evidence_id' => ["Cần có bằng chứng loại {$requiredType?->getLabel()}."],
            ]);
        }

        return $evidence;
    }

    private function requiredEvidence(
        ServiceRequest $request,
        string $action,
        ?EvidenceType $candidate,
    ): ?EvidenceType {
        if ($candidate === null || $request->deliveryOrder === null) {
            return $candidate;
        }

        $policyKey = $action === 'pickup' ? 'pickup' : 'delivery';
        $codRequiresEvidence = $request->deliveryOrder->is_cod
            && in_array($action, ['pickup', 'deliver'], true);

        return ($codRequiresEvidence
            || (bool) data_get($request->deliveryOrder->proof_policy, $policyKey, false))
            ? $candidate
            : null;
    }

    private function assertGeofence(
        ServiceRequest $request,
        ?string $stopType,
        float $latitude,
        float $longitude,
        mixed $reason,
    ): float {
        if ($stopType === null) {
            return 0;
        }

        $stop = $request->stops()->where('stop_type', $stopType)->firstOrFail();
        $distance = $this->distanceMeters(
            $latitude,
            $longitude,
            $stop->latitude,
            $stop->longitude,
        );

        if ($distance > (float) config('execution.geofence_radius_meters', 300)
            && (! is_string($reason) || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'out_of_geofence_reason' => ['Cần nêu lý do khi thao tác ngoài phạm vi điểm dừng.'],
            ]);
        }

        return $distance;
    }

    /** @param array<string, mixed> $data */
    private function preparePaymentForSettlement(
        ServiceRequest $request,
        User $driver,
        array $data,
    ): void {
        $payment = Payment::query()
            ->where('service_request_id', $request->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($payment->method === PaymentMethod::Cash) {
            if (! isset($data['cash_collected'])) {
                throw ValidationException::withMessages([
                    'cash_collected' => ['Cần nhập số tiền mặt đã thu cho phương thức thanh toán tiền mặt.'],
                ]);
            }
            $payment->forceFill([
                'cash_collected' => (float) $data['cash_collected'],
                'cash_confirmed_by' => $driver->id,
                'cash_confirmed_at' => now(),
            ]);
        }

        $payment->forceFill([
            'status' => PaymentStatus::SettlementPending,
            'version' => $payment->version + 1,
        ])->save();
    }

    private function distanceMeters(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB,
    ): float {
        $earthRadius = 6_371_000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA))
            * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function load(ServiceRequest $request): ServiceRequest
    {
        return $request->load([
            'quote',
            'vehicleType',
            'stops',
            'deliveryOrder',
            'rideBooking',
            'payment.settlement',
            'assignments.driverProfile.user',
            'assignments.vehicle',
            'evidences',
        ]);
    }
}
