<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => 'USER',
            'actor_key' => fake()->uuid(),
            'user_id' => User::factory(),
            'scope' => 'service-request.create.DELIVERY',
            'key' => fake()->unique()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'status' => 'PENDING',
            'expires_at' => now()->addDay(),
        ];
    }
}
