<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_type' => fake()->randomElement(ServiceType::cases()),
            'vehicle_type_id' => VehicleType::factory(),
            'base_distance_km' => 3,
            'base_fare' => 18_000,
            'price_per_extra_km' => 5_000,
            'driver_rate' => 0.88,
            'currency' => 'VND',
            'effective_from' => now()->subDay(),
            'effective_to' => null,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
