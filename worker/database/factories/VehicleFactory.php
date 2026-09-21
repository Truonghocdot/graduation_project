<?php

namespace Database\Factories;

use App\Enums\ReviewableStatus;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'plate_number' => strtoupper(fake()->unique()->bothify('??-####')),
            'status' => ReviewableStatus::Approved,
            'is_selected' => true,
        ];
    }
}
