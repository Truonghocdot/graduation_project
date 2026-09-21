<?php

namespace Database\Factories;

use App\Enums\PayerType;
use App\Models\DeliveryOrder;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'sender_user_id' => User::factory(),
            'recipient_user_id' => null,
            'payer_type' => PayerType::Orderer,
            'goods_type' => 'GENERAL',
            'goods_description' => fake()->optional()->sentence(),
            'weight_kg' => 5,
            'length_cm' => 20,
            'width_cm' => 20,
            'height_cm' => 20,
            'declared_value' => 0,
            'is_cod' => false,
            'cod_amount' => 0,
            'list_type' => 'ORIGINAL',
            'proof_policy' => [],
        ];
    }
}
