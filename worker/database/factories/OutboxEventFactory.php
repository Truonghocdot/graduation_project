<?php

namespace Database\Factories;

use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => 'DELIVERY_SEARCH_REQUESTED',
            'aggregate_type' => 'SERVICE_REQUEST',
            'aggregate_id' => fake()->numberBetween(1, 10_000),
            'aggregate_version' => 1,
            'payload' => [],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ];
    }
}
