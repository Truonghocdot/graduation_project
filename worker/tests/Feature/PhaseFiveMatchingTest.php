<?php

use App\Contracts\Matching\DriverPresenceStore;
use App\Enums\BookingType;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverOfferStatus;
use App\Enums\DriverReviewStatus;
use App\Enums\ReviewableStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\Assignment;
use App\Models\DriverOffer;
use App\Models\DriverProfile;
use App\Models\DriverServiceCapability;
use App\Models\LedgerAccount;
use App\Models\PricingRule;
use App\Models\Quote;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\ServiceStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Services\Matching\DriverMatchingService;
use App\Services\Matching\OfferExpiryService;
use App\Services\Matching\OfferResponseService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeDriverPresenceStore;

/** @return array{request: ServiceRequest, customer: User, vehicleType: VehicleType} */
function phaseFiveRequest(): array
{
    $customer = User::factory()->create();
    $vehicleType = VehicleType::factory()->create([
        'unique_key' => 'MATCH_MOTORBIKE',
        'is_active' => true,
    ]);
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Delivery,
        'vehicle_type_id' => $vehicleType->id,
        'effective_from' => now()->subDay(),
    ]);
    $account = LedgerAccount::query()->create([
        'owner_type' => 'USER',
        'owner_user_id' => $customer->id,
        'code' => 'MATCH:USER:'.$customer->id,
        'account_type' => 'WALLET_LIABILITY',
        'currency' => 'VND',
        'status' => 'ACTIVE',
    ]);
    Wallet::query()->create([
        'user_id' => $customer->id,
        'ledger_account_id' => $account->id,
        'currency' => 'VND',
        'balance' => 100_000,
        'status' => 'ACTIVE',
    ]);
    $quote = Quote::query()->create([
        'requested_by' => $customer->id,
        'service_type' => ServiceType::Delivery,
        'vehicle_type_id' => $vehicleType->id,
        'pricing_rule_id' => $rule->id,
        'booking_type' => 'NOW',
        'pickup_snapshot' => ['address' => 'Pickup', 'latitude' => 10.77, 'longitude' => 106.68],
        'dropoff_snapshot' => ['address' => 'Dropoff', 'latitude' => 10.78, 'longitude' => 106.69],
        'service_payload' => ['goods_type' => 'GENERAL', 'weight_kg' => 5, 'is_cod' => false, 'cod_amount' => 0],
        'route_snapshot' => ['provider' => 'fake', 'distance_meters' => 2_000, 'duration_seconds' => 600],
        'distance_meters' => 2_000,
        'duration_seconds' => 600,
        'base_fare' => 18_000,
        'gross_fare' => 18_000,
        'customer_payable' => 18_000,
        'driver_rate' => 0.88,
        'currency' => 'VND',
        'status' => 'USED',
        'used_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);
    $request = ServiceRequest::query()->create([
        'service_type' => ServiceType::Delivery,
        'created_by' => $customer->id,
        'vehicle_type_id' => $vehicleType->id,
        'quote_id' => $quote->id,
        'status' => ServiceRequestStatus::SearchingDriver,
        'booking_type' => 'NOW',
        'search_started_at' => now(),
        'version' => 1,
    ]);
    ServiceStop::query()->create([
        'service_request_id' => $request->id,
        'stop_type' => 'PICKUP',
        'address' => 'Pickup',
        'latitude' => 10.77,
        'longitude' => 106.68,
    ]);
    ServiceStop::query()->create([
        'service_request_id' => $request->id,
        'stop_type' => 'DROPOFF',
        'address' => 'Dropoff',
        'latitude' => 10.78,
        'longitude' => 106.69,
    ]);

    return ['request' => $request, 'customer' => $customer, 'vehicleType' => $vehicleType];
}

function phaseFiveDriver(VehicleType $vehicleType): array
{
    $driver = User::factory()->create(['password' => Hash::make('password')]);
    $profile = DriverProfile::query()->create([
        'user_id' => $driver->id,
        'review_status' => DriverReviewStatus::Approved,
        'availability_status' => DriverAvailabilityStatus::Online,
        'cod_limit' => 8_000_000,
    ]);
    $vehicle = Vehicle::query()->create([
        'driver_profile_id' => $profile->id,
        'vehicle_type_id' => $vehicleType->id,
        'plate_number' => strtoupper(fake()->unique()->bothify('59A-#####')),
        'status' => ReviewableStatus::Approved,
        'is_selected' => true,
    ]);
    DriverServiceCapability::query()->create([
        'driver_profile_id' => $profile->id,
        'vehicle_type_id' => $vehicleType->id,
        'service_type' => ServiceType::Delivery,
        'is_active' => true,
    ]);

    return ['user' => $driver, 'profile' => $profile, 'vehicle' => $vehicle];
}

test('creates a matching batch only for eligible nearby drivers', function () {
    $setup = phaseFiveRequest();
    $eligible = phaseFiveDriver($setup['vehicleType']);
    $ineligible = phaseFiveDriver($setup['vehicleType']);
    $ineligible['profile']->forceFill([
        'availability_status' => DriverAvailabilityStatus::Offline,
    ])->save();
    $presence = new FakeDriverPresenceStore;
    $presence->nearbyDrivers = [
        ['driver_profile_id' => $eligible['profile']->id, 'distance_meters' => 400.0],
        ['driver_profile_id' => $ineligible['profile']->id, 'distance_meters' => 500.0],
    ];
    $this->app->instance(DriverPresenceStore::class, $presence);

    $offers = app(DriverMatchingService::class)->dispatch($setup['request']);

    expect($offers)->toHaveCount(1)
        ->and($offers->first()->driver_profile_id)->toBe($eligible['profile']->id);
    $this->assertDatabaseHas('driver_offers', [
        'service_request_id' => $setup['request']->id,
        'driver_profile_id' => $eligible['profile']->id,
        'batch_number' => 1,
        'status' => DriverOfferStatus::Pending->value,
    ]);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'OFFER_CREATED']);
});

test('accepting the same offer twice is idempotent and creates one assignment', function () {
    $setup = phaseFiveRequest();
    $driver = phaseFiveDriver($setup['vehicleType']);
    $presence = new FakeDriverPresenceStore;
    $presence->nearbyDrivers = [
        ['driver_profile_id' => $driver['profile']->id, 'distance_meters' => 400.0],
    ];
    $this->app->instance(DriverPresenceStore::class, $presence);
    $offer = app(DriverMatchingService::class)->dispatch($setup['request'])->firstOrFail();

    $first = app(OfferResponseService::class)->respond($driver['user'], $offer, 'accept', 'accept-key');
    $second = app(OfferResponseService::class)->respond($driver['user'], $offer, 'accept', 'accept-key');

    expect($first->status)->toBe(DriverOfferStatus::Accepted)
        ->and($second->status)->toBe(DriverOfferStatus::Accepted)
        ->and(Assignment::query()->count())->toBe(1);
    $this->assertDatabaseHas('service_requests', [
        'id' => $setup['request']->id,
        'status' => ServiceRequestStatus::DriverArrivingPickup->value,
    ]);
    $this->assertDatabaseHas('driver_profiles', [
        'id' => $driver['profile']->id,
        'availability_status' => DriverAvailabilityStatus::Busy->value,
    ]);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'DRIVER_ASSIGNED']);
});

test('the second driver cannot win a request after the first driver is assigned', function () {
    $setup = phaseFiveRequest();
    $first = phaseFiveDriver($setup['vehicleType']);
    $second = phaseFiveDriver($setup['vehicleType']);
    $presence = new FakeDriverPresenceStore;
    $presence->nearbyDrivers = [
        ['driver_profile_id' => $first['profile']->id, 'distance_meters' => 400.0],
        ['driver_profile_id' => $second['profile']->id, 'distance_meters' => 500.0],
    ];
    $this->app->instance(DriverPresenceStore::class, $presence);
    $offers = app(DriverMatchingService::class)->dispatch($setup['request']);

    app(OfferResponseService::class)->respond($first['user'], $offers[0], 'accept', 'first-key');

    expect(fn () => app(OfferResponseService::class)->respond($second['user'], $offers[1], 'accept', 'second-key'))
        ->toThrow(ValidationException::class);
    expect(Assignment::query()->count())->toBe(1);
});

test('expires pending offers and records an outbox event', function () {
    $setup = phaseFiveRequest();
    $driver = phaseFiveDriver($setup['vehicleType']);
    $offer = DriverOffer::query()->create([
        'service_request_id' => $setup['request']->id,
        'driver_profile_id' => $driver['profile']->id,
        'batch_number' => 1,
        'status' => DriverOfferStatus::Pending,
        'estimated_pickup_distance_meters' => 500,
        'estimated_pickup_seconds' => 60,
        'estimated_driver_earning' => 15_000,
        'offered_at' => now()->subMinute(),
        'expires_at' => now()->subSecond(),
    ]);

    expect(app(OfferExpiryService::class)->expire())->toBe(1);
    $this->assertDatabaseHas('driver_offers', [
        'id' => $offer->id,
        'status' => DriverOfferStatus::Expired->value,
    ]);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'OFFER_EXPIRED']);
});

test('customer snapshot is isolated from another customer', function () {
    $setup = phaseFiveRequest();
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->getJson('/api/v1/service-requests/'.$setup['request']->public_id)
        ->assertNotFound();
});

test('driver lists and accepts only their own offer through HTTPS API', function () {
    $this->seed(RoleSeeder::class);
    $setup = phaseFiveRequest();
    $driver = phaseFiveDriver($setup['vehicleType']);
    $roleId = Role::query()->where('key', RoleKey::Driver->value)->value('id');
    $driver['user']->roles()->attach($roleId, ['granted_at' => now()]);
    $offer = DriverOffer::query()->create([
        'service_request_id' => $setup['request']->id,
        'driver_profile_id' => $driver['profile']->id,
        'batch_number' => 1,
        'status' => DriverOfferStatus::Pending,
        'estimated_pickup_distance_meters' => 500,
        'estimated_pickup_seconds' => 60,
        'estimated_driver_earning' => 15_000,
        'offered_at' => now(),
        'expires_at' => now()->addMinute(),
    ]);
    $driver['profile']->forceFill([
        'availability_status' => DriverAvailabilityStatus::Offered,
    ])->save();
    Sanctum::actingAs($driver['user'], ['driver:*']);

    $this->getJson('/api/v1/driver/offers')
        ->assertOk()
        ->assertJsonPath('data.0.id', $offer->public_id);
    $this->postJson('/api/v1/driver/offers/'.$offer->public_id.'/respond', [
        'action' => 'accept',
    ], ['Idempotency-Key' => 'driver-api-accept'])
        ->assertOk()
        ->assertJsonPath('data.status', DriverOfferStatus::Accepted->value);

    $this->assertDatabaseCount('assignments', 1);
});

test('customer can restore current state from the snapshot endpoint', function () {
    $setup = phaseFiveRequest();
    Sanctum::actingAs($setup['customer'], ['customer:*']);

    $this->getJson('/api/v1/service-requests/'.$setup['request']->public_id)
        ->assertOk()
        ->assertJsonPath('data.id', $setup['request']->public_id)
        ->assertJsonPath('data.status', ServiceRequestStatus::SearchingDriver->value);
});

test('activates scheduled requests when their matching time is due', function () {
    $setup = phaseFiveRequest();
    $setup['request']->forceFill([
        'status' => ServiceRequestStatus::Scheduled,
        'booking_type' => BookingType::Scheduled,
        'scheduled_at' => now()->subSecond(),
        'search_started_at' => null,
    ])->save();

    expect(app(DriverMatchingService::class)->activateDueScheduled())->toBe(1)
        ->and($setup['request']->fresh()?->status)->toBe(ServiceRequestStatus::SearchingDriver)
        ->and($setup['request']->fresh()?->search_started_at)->not->toBeNull();
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'SCHEDULED_SEARCH_STARTED']);
});
