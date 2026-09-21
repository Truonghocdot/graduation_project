<?php

namespace Database\Factories;

use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unique_key' => fake()->unique()->regexify('[A-Z]{5}_[0-9]{2}'),
            'name' => fake()->words(2, true),
            'passenger_capacity' => fake()->numberBetween(1, 7),
            'max_weight_kg' => fake()->numberBetween(20, 300),
            'max_length_cm' => fake()->numberBetween(50, 200),
            'max_width_cm' => fake()->numberBetween(40, 150),
            'max_height_cm' => fake()->numberBetween(40, 150),
            'is_active' => true,
        ];
    }
}
