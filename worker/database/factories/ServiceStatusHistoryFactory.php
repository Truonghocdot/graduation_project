<?php

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceStatusHistory>
 */
class ServiceStatusHistoryFactory extends Factory
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
            'version' => 1,
            'from_status' => null,
            'to_status' => ServiceRequestStatus::SearchingDriver,
            'actor_user_id' => User::factory(),
            'actor_type' => 'CUSTOMER',
            'reason_code' => null,
            'metadata' => [],
            'correlation_id' => fake()->uuid(),
            'created_at' => now(),
        ];
    }
}
