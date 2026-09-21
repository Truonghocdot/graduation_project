<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('SAVE##??')),
            'name' => fake()->sentence(3),
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 10_000,
            'max_discount_amount' => null,
            'service_scope' => null,
            'minimum_order_amount' => 0,
            'total_usage_limit' => null,
            'per_user_usage_limit' => null,
            'max_restore_count' => 1,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
