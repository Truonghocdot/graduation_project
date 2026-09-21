<?php

namespace Database\Factories;

use App\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerAccount>
 */
class LedgerAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => 'SYSTEM',
            'owner_user_id' => null,
            'code' => 'SYSTEM:'.fake()->unique()->bothify('ACCOUNT-####'),
            'account_type' => 'PAYMENT_CLEARING',
            'currency' => 'VND',
            'status' => 'ACTIVE',
        ];
    }
}
