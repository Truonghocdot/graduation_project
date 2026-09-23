<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\Role;
use App\Models\ServiceEvidence;
use App\Models\User;
use App\Services\Finance\DriverDailyCodLimitService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ExecutionScenarioBuilder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** @return array<string, string|float> */
function executionTransition(
    string $action,
    float $latitude,
    float $longitude,
    ?string $evidenceId = null,
    array $extra = [],
): array {
    return array_filter([
        'action' => $action,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'evidence_id' => $evidenceId,
        ...$extra,
    ], fn (mixed $value): bool => $value !== null);
}

function uploadExecutionEvidence(
    mixed $test,
    string $requestId,
    string $type,
    string $name,
): string {
    return $test->post(
        "/api/v1/driver/service-requests/{$requestId}/evidence",
        [
            'evidence_type' => $type,
            'file' => UploadedFile::fake()->image($name),
            'latitude' => 10.77,
            'longitude' => 106.68,
        ],
        ['Accept' => 'application/json'],
    )->assertCreated()->json('data.id');
}

test('executes a delivery in order with private evidence cash settlement and idempotency', function () {
    Storage::fake('local');
    $scenario = ExecutionScenarioBuilder::create(
        ServiceType::Delivery,
        PaymentMethod::Cash,
    );
    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $url = '/api/v1/driver/service-requests/'.$scenario['request']->public_id.'/transition';

    $this->postJson($url, executionTransition('arrive_pickup', 10.77, 106.68), [
        'Idempotency-Key' => 'delivery-arrive',
    ])->assertOk()
        ->assertJsonPath('data.status', ServiceRequestStatus::AtPickup->value);

    $pickupEvidence = uploadExecutionEvidence(
        $this,
        $scenario['request']->public_id,
        'PICKUP',
        'pickup.jpg',
    );
    $pickupPayload = executionTransition('pickup', 10.77, 106.68, $pickupEvidence);
    $this->postJson($url, $pickupPayload, ['Idempotency-Key' => 'delivery-pickup'])
        ->assertOk()
        ->assertJsonPath('data.status', ServiceRequestStatus::PickedUp->value);
    $this->postJson($url, $pickupPayload, ['Idempotency-Key' => 'delivery-pickup'])
        ->assertOk()
        ->assertJsonPath('data.status', ServiceRequestStatus::PickedUp->value);

    $this->postJson($url, executionTransition('start_delivery', 10.77, 106.68), [
        'Idempotency-Key' => 'delivery-start',
    ])->assertOk()
        ->assertJsonPath('data.status', ServiceRequestStatus::InDelivery->value);

    $deliveryEvidence = uploadExecutionEvidence(
        $this,
        $scenario['request']->public_id,
        'DELIVERY',
        'delivered.jpg',
    );
    $this->postJson($url, executionTransition(
        'deliver',
        10.78,
        106.69,
        $deliveryEvidence,
        ['cash_collected' => 100_000],
    ), ['Idempotency-Key' => 'delivery-complete'])
        ->assertOk()
        ->assertJsonPath('data.status', ServiceRequestStatus::Completed->value)
        ->assertJsonPath('data.payment.status', PaymentStatus::Settled->value);

    $this->assertDatabaseCount('service_evidences', 2);
    $this->assertDatabaseCount('settlements', 1);
    $this->assertDatabaseCount('service_status_histories', 5);
    $this->assertDatabaseHas('payments', [
        'id' => $scenario['payment']->id,
        'cash_collected' => 100_000,
        'status' => PaymentStatus::Settled->value,
    ]);
    $this->assertDatabaseHas('wallets', [
        'id' => $scenario['driver_wallet']->id,
        'balance' => -12_000,
    ]);
});

test('does not allow delivery transitions to skip required states', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Delivery);
    Sanctum::actingAs($scenario['driver'], ['driver:*']);

    $this->postJson(
        '/api/v1/driver/service-requests/'.$scenario['request']->public_id.'/transition',
        executionTransition('deliver', 10.78, 106.69),
        ['Idempotency-Key' => 'skip-delivery-state'],
    )->assertUnprocessable()->assertJsonValidationErrors('action');

    expect($scenario['request']->fresh()?->status)
        ->toBe(ServiceRequestStatus::DriverArrivingPickup);
});

test('requires an audited reason when transitioning outside the geofence', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $url = '/api/v1/driver/service-requests/'.$scenario['request']->public_id.'/transition';

    $this->postJson($url, executionTransition('arrive', 11.0, 107.0), [
        'Idempotency-Key' => 'ride-outside-no-reason',
    ])->assertUnprocessable()->assertJsonValidationErrors('out_of_geofence_reason');

    $this->postJson($url, executionTransition(
        'arrive',
        11.0,
        107.0,
        extra: ['out_of_geofence_reason' => 'ROAD_BLOCKED'],
    ), ['Idempotency-Key' => 'ride-outside-reason'])
        ->assertOk()
        ->assertJsonPath('data.status', ServiceRequestStatus::DriverArrived->value);

    $this->assertDatabaseHas('service_status_histories', [
        'service_request_id' => $scenario['request']->id,
        'reason_code' => 'ROAD_BLOCKED',
    ]);
});

test('keeps evidence private to customer assigned driver and admin', function () {
    Storage::fake('local');
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Delivery);
    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $evidenceId = uploadExecutionEvidence(
        $this,
        $scenario['request']->public_id,
        'PICKUP',
        'private.jpg',
    );
    $path = ServiceEvidence::query()->where('public_id', $evidenceId)->value('storage_path');
    Storage::disk('local')->assertExists($path);
    $this->get("/api/v1/service-evidence/{$evidenceId}/file")->assertOk();

    Sanctum::actingAs($scenario['customer'], ['customer:*']);
    $this->get("/api/v1/service-evidence/{$evidenceId}/file")->assertOk();

    Sanctum::actingAs(User::factory()->create(), ['customer:*']);
    $this->get("/api/v1/service-evidence/{$evidenceId}/file")->assertNotFound();

    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    Sanctum::actingAs($admin, ['admin:*']);
    $this->get("/api/v1/service-evidence/{$evidenceId}/file")->assertOk();
});

test('records COD advance and collection separately from driver earnings', function () {
    Storage::fake('local');
    $scenario = ExecutionScenarioBuilder::create(
        ServiceType::Delivery,
        PaymentMethod::Cash,
        isCod: true,
    );
    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $url = '/api/v1/driver/service-requests/'.$scenario['request']->public_id.'/transition';
    $this->postJson($url, executionTransition('arrive_pickup', 10.77, 106.68), [
        'Idempotency-Key' => 'cod-arrive',
    ])->assertOk();
    $pickup = uploadExecutionEvidence($this, $scenario['request']->public_id, 'PICKUP', 'cod-pickup.jpg');
    $this->postJson($url, executionTransition('pickup', 10.77, 106.68, $pickup), [
        'Idempotency-Key' => 'cod-pickup',
    ])->assertOk();
    $dailyLimit = app(DriverDailyCodLimitService::class);
    expect($dailyLimit->remaining($scenario['profile']->fresh()))->toBe(7_500_000.0)
        ->and($dailyLimit->remaining($scenario['profile']->fresh(), now()->addDay()))
        ->toBe(8_000_000.0);
    $this->postJson($url, executionTransition('start_delivery', 10.77, 106.68), [
        'Idempotency-Key' => 'cod-start',
    ])->assertOk();
    $delivery = uploadExecutionEvidence($this, $scenario['request']->public_id, 'DELIVERY', 'cod-delivery.jpg');
    $this->postJson($url, executionTransition(
        'deliver',
        10.78,
        106.69,
        $delivery,
        ['cash_collected' => 100_000, 'cod_collected' => 500_000],
    ), ['Idempotency-Key' => 'cod-deliver'])->assertOk();

    $this->assertDatabaseHas('cod_accounts', [
        'delivery_order_id' => $scenario['request']->id,
        'advanced_amount' => 500_000,
        'collected_amount' => 500_000,
        'status' => 'CLOSED',
    ]);
    $this->assertDatabaseCount('cod_transactions', 2);
    $this->assertDatabaseHas('settlements', [
        'payment_id' => $scenario['payment']->id,
        'driver_gross_earning' => 100_000,
    ]);
});

test('keeps only the latest assigned driver location snapshot', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $capturedAt = now()->subSecond();

    $this->putJson('/api/v1/driver/location', [
        'latitude' => 10.771,
        'longitude' => 106.681,
        'accuracy' => 5,
        'heading' => 90,
        'speed' => 8,
        'captured_at' => $capturedAt->toISOString(),
    ])->assertOk()
        ->assertJsonPath('data.location.lat', 10.771);

    $this->putJson('/api/v1/driver/location', [
        'latitude' => 10.770,
        'longitude' => 106.680,
        'accuracy' => 5,
        'captured_at' => $capturedAt->subSecond()->toISOString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('captured_at');

    $this->assertDatabaseCount('driver_last_locations', 1);
    $this->assertDatabaseHas('driver_last_locations', [
        'driver_profile_id' => $scenario['profile']->id,
    ]);
});
