<?php

namespace Database\Factories;

use App\Enums\BookingType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Models\Quote;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'service_type' => ServiceType::Delivery,
            'vehicle_type_id' => VehicleType::factory(),
            'pricing_rule_id' => PricingRule::factory(),
            'booking_type' => BookingType::Now,
            'scheduled_at' => null,
            'pickup_snapshot' => [
                'address' => 'Pickup address',
                'latitude' => 10.77,
                'longitude' => 106.68,
            ],
            'dropoff_snapshot' => [
                'address' => 'Dropoff address',
                'latitude' => 10.78,
                'longitude' => 106.70,
            ],
            'service_payload' => ['goods_type' => 'GENERAL'],
            'route_snapshot' => [
                'provider' => 'fake',
                'distance_meters' => 4_000,
                'duration_seconds' => 900,
                'encoded_polyline' => null,
                'metadata' => [],
            ],
            'distance_meters' => 4_000,
            'duration_seconds' => 900,
            'base_fare' => 18_000,
            'extra_distance_fare' => 5_000,
            'surcharge_amount' => 0,
            'gross_fare' => 23_000,
            'voucher_discount' => 0,
            'customer_payable' => 23_000,
            'driver_rate' => 0.88,
            'currency' => 'VND',
            'status' => QuoteStatus::Active,
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ];
    }

    public function forPricingRule(PricingRule $pricingRule): static
    {
        return $this->state(fn (): array => [
            'pricing_rule_id' => $pricingRule->id,
            'vehicle_type_id' => $pricingRule->vehicle_type_id,
            'service_type' => $pricingRule->service_type,
        ]);
    }
}
