<?php

use App\Contracts\Maps\MapProvider;
use App\Data\Maps\RouteResult;
use App\Enums\BookingType;
use App\Enums\DiscountType;
use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Voucher;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeMapProvider;

function quotePayload(
    VehicleType $vehicleType,
    ServiceType $serviceType = ServiceType::Delivery,
): array {
    return [
        'service_type' => $serviceType->value,
        'vehicle_type_id' => $vehicleType->public_id,
        'booking_type' => BookingType::Now->value,
        'pickup' => [
            'address' => '1 Nguyen Hue, District 1',
            'latitude' => 10.773,
            'longitude' => 106.704,
        ],
        'dropoff' => [
            'address' => '1 Vo Van Tan, District 3',
            'latitude' => 10.780,
            'longitude' => 106.690,
        ],
        'service_payload' => $serviceType === ServiceType::Delivery
            ? ['goods_type' => 'GENERAL', 'weight_kg' => 5]
            : ['passenger_count' => 2],
    ];
}

/** @return array{vehicle: VehicleType, rule: PricingRule} */
function createQuoteCatalog(
    ServiceType $serviceType,
    string $vehicleKey,
    float $baseFare,
    float $extraFare,
    ?int $passengerCapacity = 1,
): array {
    $vehicle = VehicleType::factory()->create([
        'unique_key' => $vehicleKey,
        'passenger_capacity' => $passengerCapacity,
        'is_active' => true,
    ]);
    $rule = PricingRule::factory()->create([
        'service_type' => $serviceType,
        'vehicle_type_id' => $vehicle->id,
        'base_distance_km' => 3,
        'base_fare' => $baseFare,
        'price_per_extra_km' => $extraFare,
        'driver_rate' => 0.88,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
        'is_active' => true,
    ]);

    return ['vehicle' => $vehicle, 'rule' => $rule];
}

test('requires authentication to create a quote', function () {
    $this->postJson('/api/v1/quotes', [])->assertUnauthorized();
});

test('creates a delivery motorbike quote with route pricing and voucher snapshots', function () {
    $this->freezeTime();
    $catalog = createQuoteCatalog(ServiceType::Delivery, 'MOTORBIKE', 18_000, 5_000);
    $user = User::factory()->create();
    $voucher = Voucher::factory()->create([
        'code' => 'SAVE5K',
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 5_000,
        'created_by' => $user->id,
    ]);
    $map = new FakeMapProvider(new RouteResult(
        provider: 'fake-goong',
        distanceMeters: 4_200,
        durationSeconds: 900,
        encodedPolyline: 'encoded-route',
        metadata: ['summary' => 'test route'],
    ));
    $this->app->instance(MapProvider::class, $map);
    Sanctum::actingAs($user, ['customer:*']);
    $payload = quotePayload($catalog['vehicle']);
    $payload['voucher_code'] = $voucher->code;

    $response = $this->postJson('/api/v1/quotes', $payload)->assertCreated();

    expect((float) $response->json('data.pricing.base_fare'))->toBe(18_000.0)
        ->and((float) $response->json('data.pricing.extra_distance_fare'))->toBe(6_000.0)
        ->and((float) $response->json('data.pricing.gross_fare'))->toBe(24_000.0)
        ->and((float) $response->json('data.pricing.voucher_discount'))->toBe(5_000.0)
        ->and((float) $response->json('data.pricing.customer_payable'))->toBe(19_000.0)
        ->and($response->json('data.route.provider'))->toBe('fake-goong')
        ->and($response->json('data.service_payload.voucher.code'))->toBe('SAVE5K')
        ->and($response->json('data.expires_at'))->toBe(now()->addMinutes(5)->startOfSecond()->toJSON())
        ->and($map->calls)->toHaveCount(1)
        ->and($map->calls[0]['vehicle_type_key'])->toBe('MOTORBIKE');

    $this->assertDatabaseHas('quotes', [
        'requested_by' => $user->id,
        'pricing_rule_id' => $catalog['rule']->id,
        'gross_fare' => 24_000,
        'voucher_discount' => 5_000,
        'customer_payable' => 19_000,
    ]);
    $this->assertDatabaseCount('voucher_redemptions', 0);
});

test('creates a drive car quote with the configured extra kilometre rate', function () {
    $catalog = createQuoteCatalog(ServiceType::Drive, 'CAR_4_SEAT', 30_000, 10_000, 4);
    $user = User::factory()->create();
    $map = new FakeMapProvider(new RouteResult(
        provider: 'fake-goong',
        distanceMeters: 5_100,
        durationSeconds: 1_100,
        encodedPolyline: null,
    ));
    $this->app->instance(MapProvider::class, $map);
    Sanctum::actingAs($user, ['customer:*']);

    $response = $this->postJson(
        '/api/v1/quotes',
        quotePayload($catalog['vehicle'], ServiceType::Drive),
    )->assertCreated();

    expect((float) $response->json('data.pricing.base_fare'))->toBe(30_000.0)
        ->and((float) $response->json('data.pricing.extra_distance_fare'))->toBe(21_000.0)
        ->and((float) $response->json('data.pricing.customer_payable'))->toBe(51_000.0)
        ->and($response->json('data.service_type'))->toBe(ServiceType::Drive->value);
});

test('creates a quote without requiring configured service areas', function () {
    $catalog = createQuoteCatalog(ServiceType::Delivery, 'MOTORBIKE', 18_000, 5_000);
    $user = User::factory()->create();
    $map = new FakeMapProvider(new RouteResult('fake', 1_000, 300, null));
    $this->app->instance(MapProvider::class, $map);
    Sanctum::actingAs($user, ['customer:*']);
    $payload = quotePayload($catalog['vehicle']);
    $payload['dropoff']['latitude'] = 12.0;

    $this->postJson('/api/v1/quotes', $payload)->assertCreated();

    expect($map->calls)->toHaveCount(1);
    $this->assertDatabaseCount('quotes', 1);
});

test('rejects passenger count above vehicle capacity', function () {
    $catalog = createQuoteCatalog(ServiceType::Drive, 'CAR_4_SEAT', 30_000, 10_000, 4);
    $user = User::factory()->create();
    $map = new FakeMapProvider(new RouteResult('fake', 1_000, 300, null));
    $this->app->instance(MapProvider::class, $map);
    Sanctum::actingAs($user, ['customer:*']);
    $payload = quotePayload($catalog['vehicle'], ServiceType::Drive);
    $payload['service_payload']['passenger_count'] = 5;

    $this->postJson('/api/v1/quotes', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_payload.passenger_count');

    expect($map->calls)->toBeEmpty();
});

test('requires a future scheduled time for scheduled quotes', function () {
    $catalog = createQuoteCatalog(ServiceType::Delivery, 'MOTORBIKE', 18_000, 5_000);
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);
    $payload = quotePayload($catalog['vehicle']);
    $payload['booking_type'] = BookingType::Scheduled->value;

    $this->postJson('/api/v1/quotes', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');
});

test('returns service unavailable without persisting when the map provider fails', function () {
    $catalog = createQuoteCatalog(ServiceType::Delivery, 'MOTORBIKE', 18_000, 5_000);
    $this->app->instance(MapProvider::class, new FakeMapProvider(fails: true));
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->postJson('/api/v1/quotes', quotePayload($catalog['vehicle']))
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'MAP_ROUTE_UNAVAILABLE');

    $this->assertDatabaseCount('quotes', 0);
});
