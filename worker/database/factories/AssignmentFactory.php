<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\DriverProfile;
use App\Models\ServiceRequest;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
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
            'vehicle_id' => Vehicle::factory(),
            'accepted_offer_id' => null,
            'status' => AssignmentStatus::Active,
            'assigned_at' => now(),
            'closed_at' => null,
            'close_reason_code' => null,
        ];
    }
}
