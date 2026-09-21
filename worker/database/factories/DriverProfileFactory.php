<?php

namespace Database\Factories;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverReviewStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DriverProfile> */
class DriverProfileFactory extends Factory
{
    protected $model = DriverProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'review_status' => DriverReviewStatus::Approved,
            'availability_status' => DriverAvailabilityStatus::Online,
            'cod_limit' => 8_000_000,
            'submitted_at' => now()->subDay(),
        ];
    }
}
