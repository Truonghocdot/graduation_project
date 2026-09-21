<?php

use App\Enums\DriverAvailabilityStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceType;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\Driver\DriverReviewService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleTypeSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DriverApplicationBuilder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, VehicleTypeSeeder::class]);
});

test('allows an eligible approved driver to go online and offline', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    $driver = User::factory()->create();
    $profile = DriverApplicationBuilder::submitted(
        $driver,
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
        [ServiceType::Delivery, ServiceType::Drive],
    );
    app(DriverReviewService::class)->approve($profile, $admin);
    Sanctum::actingAs($driver, ['driver:*']);

    $this->putJson('/api/v1/driver/availability/online', [
        'service_types' => ['DELIVERY', 'DRIVE'],
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'accuracy' => 5,
        'heading' => 90,
        'speed' => 0,
        'captured_at' => now()->toISOString(),
    ])->assertOk()
        ->assertJsonPath('data.availability_status', DriverAvailabilityStatus::Online->value)
        ->assertJsonPath('data.last_location.lat', 10.7769);

    $this->assertDatabaseHas('driver_last_locations', [
        'driver_profile_id' => $profile->id,
    ]);

    $this->putJson('/api/v1/driver/availability/offline')
        ->assertOk()
        ->assertJsonPath('data.availability_status', DriverAvailabilityStatus::Offline->value);
});

test('blocks an approved driver from going online with a negative wallet', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    $driver = User::factory()->create();
    $profile = DriverApplicationBuilder::submitted(
        $driver,
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
    );
    app(DriverReviewService::class)->approve($profile, $admin);
    $driver->wallet()->update(['balance' => -1]);
    Sanctum::actingAs($driver, ['driver:*']);

    $this->putJson('/api/v1/driver/availability/online', [
        'service_types' => ['DELIVERY'],
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'accuracy' => 5,
        'captured_at' => now()->toISOString(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('availability');
});

test('forbids users without the driver role from changing availability', function () {
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->putJson('/api/v1/driver/availability/online', [
        'service_types' => ['DELIVERY'],
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'accuracy' => 5,
        'captured_at' => now()->toISOString(),
    ])->assertForbidden();
});
