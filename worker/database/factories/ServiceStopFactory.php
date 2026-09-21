<?php

namespace Database\Factories;

use App\Enums\StopType;
use App\Models\ServiceRequest;
use App\Models\ServiceStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceStop>
 */
class ServiceStopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'stop_type' => StopType::Pickup,
            'address' => fake()->address(),
            'latitude' => fake()->latitude(10.7, 10.9),
            'longitude' => fake()->longitude(106.6, 106.8),
            'contact_name' => fake()->name(),
            'contact_phone' => '+849'.fake()->numerify('########'),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
