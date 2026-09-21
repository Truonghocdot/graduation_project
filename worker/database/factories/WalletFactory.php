<?php

namespace Database\Factories;

use App\Models\LedgerAccount;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ledger_account_id' => LedgerAccount::factory(),
            'currency' => 'VND',
            'balance' => 0,
            'reserved_withdrawal_amount' => 0,
            'status' => 'ACTIVE',
            'version' => 1,
        ];
    }
}
