<?php

namespace Database\Factories;

use App\Models\RideBooking;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideBooking>
 */
class RideBookingFactory extends Factory
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
            'passenger_count' => 1,
            'started_at' => null,
            'ended_at' => null,
            'route_version' => 1,
        ];
    }
}
