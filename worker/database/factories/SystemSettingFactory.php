<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'pricing.'.fake()->unique()->slug(2, '.'),
            'value' => fake()->numberBetween(1, 1_000),
            'is_public' => false,
            'updated_by' => null,
        ];
    }
}
