<?php

namespace App\Data\Pricing;

final readonly class PricingBreakdown
{
    public function __construct(
        public float $baseFare,
        public float $extraDistanceFare,
        public float $surchargeAmount,
        public float $grossFare,
        public float $voucherDiscount,
        public float $customerPayable,
        public float $driverRate,
        public string $currency,
    ) {}

    /** @return array<string, float|string> */
    public function toArray(): array
    {
        return [
            'base_fare' => $this->baseFare,
            'extra_distance_fare' => $this->extraDistanceFare,
            'surcharge_amount' => $this->surchargeAmount,
            'gross_fare' => $this->grossFare,
            'voucher_discount' => $this->voucherDiscount,
            'customer_payable' => $this->customerPayable,
            'driver_rate' => $this->driverRate,
            'currency' => $this->currency,
        ];
    }
}
