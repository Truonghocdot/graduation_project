<?php

use App\Contracts\Matching\DriverPresenceStore;
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
use Tests\Support\FakeDriverPresenceStore;

beforeEach(function () {
    $this->seed([RoleSeeder::class, VehicleTypeSeeder::class]);
});

test('driver online and offline updates the presence store with TTL', function () {
    $presence = new FakeDriverPresenceStore;
    $this->app->instance(DriverPresenceStore::class, $presence);
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);
    $driver = User::factory()->create();
    $vehicleType = VehicleType::query()->where('unique_key', 'MOTORBIKE')->firstOrFail();
    $profile = DriverApplicationBuilder::submitted($driver, $vehicleType, [ServiceType::Delivery]);
    app(DriverReviewService::class)->approve($profile, $admin);
    Sanctum::actingAs($driver, ['driver:*']);

    $this->putJson('/api/v1/driver/availability/online', [
        'service_types' => ['DELIVERY'],
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'accuracy' => 5,
        'captured_at' => now()->toISOString(),
    ])->assertOk();

    expect($presence->online[$profile->id]['services'])->toBe(['DELIVERY'])
        ->and($presence->online[$profile->id]['ttl'])->toBe(15);

    $this->putJson('/api/v1/driver/availability/offline')->assertOk();
    expect($presence->offline)->toContain($profile->id);
});
