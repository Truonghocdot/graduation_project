<?php

namespace Database\Factories;

use App\Enums\DriverOfferStatus;
use App\Models\DriverOffer;
use App\Models\DriverProfile;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverOffer>
 */
class DriverOfferFactory extends Factory
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
            'driver_profile_id' => DriverProfile::factory(),
            'batch_number' => 1,
            'status' => DriverOfferStatus::Pending,
            'estimated_pickup_distance_meters' => 500,
            'estimated_pickup_seconds' => 60,
            'estimated_driver_earning' => 15_000,
            'offered_at' => now(),
            'expires_at' => now()->addSeconds(30),
            'responded_at' => null,
        ];
    }
}
