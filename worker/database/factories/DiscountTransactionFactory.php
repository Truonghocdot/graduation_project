<?php

namespace Database\Factories;

use App\Models\DiscountTransaction;
use App\Models\Payment;
use App\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountTransaction>
 */
class DiscountTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'voucher_redemption_id' => VoucherRedemption::factory(),
            'amount' => 5_000,
            'status' => 'APPLIED',
            'applied_at' => now(),
            'restored_at' => null,
        ];
    }
}
