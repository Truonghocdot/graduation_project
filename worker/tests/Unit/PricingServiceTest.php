<?php

use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Models\SystemSetting;
use App\Models\VehicleType;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('uses the base fare as the minimum and caps the voucher at the gross fare', function () {
    $vehicle = VehicleType::factory()->create();
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Delivery,
        'vehicle_type_id' => $vehicle->id,
        'base_distance_km' => 3,
        'base_fare' => 18_000,
        'price_per_extra_km' => 5_000,
    ]);

    $result = app(PricingService::class)->calculate($rule, 2_000, 30_000);

    expect($result->baseFare)->toBe(18_000.0)
        ->and($result->extraDistanceFare)->toBe(0.0)
        ->and($result->grossFare)->toBe(18_000.0)
        ->and($result->voucherDiscount)->toBe(18_000.0)
        ->and($result->customerPayable)->toBe(0.0);
});

test('rounds fractional extra distance fares using the configured VND unit', function () {
    SystemSetting::query()->create([
        'key' => 'pricing.rounding_unit',
        'value' => 100,
        'is_public' => false,
    ]);
    $vehicle = VehicleType::factory()->create();
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Drive,
        'vehicle_type_id' => $vehicle->id,
        'base_distance_km' => 3,
        'base_fare' => 30_000,
        'price_per_extra_km' => 5_000,
    ]);

    $result = app(PricingService::class)->calculate($rule, 3_333.333);

    expect($result->extraDistanceFare)->toBe(1_700.0)
        ->and($result->grossFare)->toBe(31_700.0)
        ->and($result->customerPayable)->toBe(31_700.0);
});

test('selects the pricing version effective at the requested time', function () {
    $vehicle = VehicleType::factory()->create();
    PricingRule::factory()->create([
        'service_type' => ServiceType::Delivery,
        'vehicle_type_id' => $vehicle->id,
        'base_fare' => 18_000,
        'effective_from' => now()->subDays(2),
        'effective_to' => now()->subDay(),
    ]);
    $current = PricingRule::factory()->create([
        'service_type' => ServiceType::Delivery,
        'vehicle_type_id' => $vehicle->id,
        'base_fare' => 20_000,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    $selected = app(PricingService::class)->currentRule(ServiceType::Delivery, $vehicle);

    expect($selected->is($current))->toBeTrue();
});
