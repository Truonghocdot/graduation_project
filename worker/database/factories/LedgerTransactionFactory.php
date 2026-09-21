<?php

namespace Database\Factories;

use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerTransaction>
 */
class LedgerTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_type' => 'CUSTOMER_PAYMENT',
            'status' => 'POSTED',
            'reference_type' => 'PAYMENT',
            'reference_id' => fake()->numberBetween(1, 10_000),
            'idempotency_key' => fake()->unique()->uuid(),
            'correlation_id' => fake()->uuid(),
            'metadata' => [],
            'posted_at' => now(),
        ];
    }
}
