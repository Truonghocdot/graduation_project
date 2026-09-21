<?php

namespace Database\Factories;

use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'payer_type' => PayerType::Orderer,
            'payer_user_id' => User::factory(),
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Ready,
            'currency' => 'VND',
            'gross_fare' => 18_000,
            'voucher_discount' => 0,
            'customer_payable' => 18_000,
            'customer_payment_ledger_id' => null,
            'cash_collected' => 0,
            'version' => 1,
        ];
    }
}
