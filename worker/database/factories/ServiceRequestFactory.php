<?php

namespace Database\Factories;

use App\Enums\BookingType;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_type' => ServiceType::Delivery,
            'created_by' => User::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'quote_id' => Quote::factory(),
            'status' => ServiceRequestStatus::SearchingDriver,
            'booking_type' => BookingType::Now,
            'scheduled_at' => null,
            'search_started_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason_code' => null,
            'search_attempt' => 0,
            'version' => 1,
        ];
    }
}
