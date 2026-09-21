<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\ServiceArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceArea>
 */
class ServiceAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'service_type' => fake()->optional()->randomElement(ServiceType::cases()),
            'boundary' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [106.50, 10.60],
                    [106.90, 10.60],
                    [106.90, 11.00],
                    [106.50, 11.00],
                    [106.50, 10.60],
                ]],
            ],
            'is_active' => true,
        ];
    }
}
