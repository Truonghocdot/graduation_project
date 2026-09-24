<?php

use App\Enums\ServiceType;
use App\Models\DriverLastLocation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ExecutionScenarioBuilder;

test('customer tracking exposes status, driver and fresh location', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    DriverLastLocation::query()->create([
        'driver_profile_id' => $scenario['profile']->id,
        'last_location' => [
            'lat' => 10.771,
            'lng' => 106.681,
            'accuracy' => 5,
            'heading' => 90,
            'speed' => 8,
        ],
        'last_location_at' => now(),
        'updated_at' => now(),
    ]);
    Sanctum::actingAs($scenario['customer'], ['customer:*']);

    $this->getJson('/api/v1/service-requests/'.$scenario['request']->public_id.'/tracking')
        ->assertOk()
        ->assertJsonPath('data.status', 'DRIVER_ARRIVING')
        ->assertJsonPath('data.status_meta.phase', 'EN_ROUTE')
        ->assertJsonPath('data.driver.name', $scenario['driver']->name)
        ->assertJsonPath('data.live_location.latitude', 10.771)
        ->assertJsonPath('data.location_stale', false);
});

test('tracking endpoint does not reveal another customer request', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->getJson('/api/v1/service-requests/'.$scenario['request']->public_id.'/tracking')
        ->assertNotFound();
});
