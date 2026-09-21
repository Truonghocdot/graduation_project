<?php

use App\Enums\DriverReviewStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceType;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleTypeSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DriverApplicationBuilder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, VehicleTypeSeeder::class]);
});

test('allows an admin to approve a submitted driver application', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    Sanctum::actingAs($admin, ['*']);

    $driver = User::factory()->create(['password' => 'password123']);
    $vehicleType = VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail();
    $profile = DriverApplicationBuilder::submitted(
        $driver,
        $vehicleType,
        [ServiceType::Delivery, ServiceType::Drive],
    );

    $this->postJson("/api/v1/admin/driver-applications/{$profile->public_id}/approval")
        ->assertOk()
        ->assertJsonPath('data.review_status', DriverReviewStatus::Approved->value);

    $driverRoleId = Role::query()->where('key', RoleKey::Driver->value)->value('id');
    $this->assertDatabaseHas('user_roles', [
        'user_id' => $driver->id,
        'role_id' => $driverRoleId,
    ]);
    $this->assertDatabaseHas('wallets', [
        'user_id' => $driver->id,
        'balance' => 0,
    ]);
    $this->assertDatabaseMissing('driver_documents', [
        'driver_profile_id' => $profile->id,
        'status' => 'PENDING',
    ]);
    $this->assertDatabaseMissing('driver_service_capabilities', [
        'driver_profile_id' => $profile->id,
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $driver->phone,
        'password' => 'password123',
        'device_id' => 'driver-device-1',
        'app_type' => 'DRIVER_APP',
        'platform' => 'ANDROID',
    ])->assertOk()->assertJsonStructure(['token']);
});

test('allows an admin to reject a submitted application with a reason', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    Sanctum::actingAs($admin, ['*']);

    $driver = User::factory()->create();
    $profile = DriverApplicationBuilder::submitted(
        $driver,
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
    );

    $this->postJson("/api/v1/admin/driver-applications/{$profile->public_id}/rejection", [
        'reason_code' => 'DOCUMENT_UNREADABLE',
    ])->assertOk()
        ->assertJsonPath('data.review_status', DriverReviewStatus::Rejected->value)
        ->assertJsonPath('data.review_reason_code', 'DOCUMENT_UNREADABLE');
});

test('forbids non admins from reviewing driver applications', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer, ['customer:*']);

    $profile = DriverApplicationBuilder::submitted(
        User::factory()->create(),
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
    );

    $this->postJson("/api/v1/admin/driver-applications/{$profile->public_id}/approval")
        ->assertForbidden();
});

test('rejects driver app login before approval', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $user->phone,
        'password' => 'password123',
        'device_id' => 'driver-device-1',
        'app_type' => 'DRIVER_APP',
        'platform' => 'ANDROID',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('app_type');
});

test('suspends an approved driver and blocks driver app login', function () {
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    Sanctum::actingAs($admin, ['*']);

    $driver = User::factory()->create(['password' => 'password123']);
    $profile = DriverApplicationBuilder::submitted(
        $driver,
        VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail(),
    );
    $this->postJson("/api/v1/admin/driver-applications/{$profile->public_id}/approval")
        ->assertOk();

    $this->postJson("/api/v1/admin/drivers/{$profile->public_id}/suspension", [
        'reason_code' => 'SAFETY_REVIEW',
    ])->assertOk()
        ->assertJsonPath('data.review_status', DriverReviewStatus::Suspended->value);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $driver->phone,
        'password' => 'password123',
        'device_id' => 'driver-device-1',
        'app_type' => 'DRIVER_APP',
        'platform' => 'ANDROID',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('app_type');
});
