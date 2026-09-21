<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherRedemption>
 */
class VoucherRedemptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'user_id' => User::factory(),
            'service_request_id' => ServiceRequest::factory(),
            'status' => 'USED',
            'discount_amount' => 5_000,
            'used_at' => now(),
            'restored_at' => null,
            'restore_reason_code' => null,
            'restore_count' => 0,
        ];
    }
}
