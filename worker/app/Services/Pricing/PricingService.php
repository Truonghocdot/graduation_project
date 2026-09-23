<?php

namespace App\Services\Pricing;

use App\Data\Pricing\PricingBreakdown;
use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Models\SystemSetting;
use App\Models\VehicleType;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class PricingService
{
    public function currentRule(
        ServiceType $serviceType,
        VehicleType $vehicleType,
        ?CarbonInterface $at = null,
    ): PricingRule {
        $at ??= now();

        $rule = PricingRule::query()
            ->where('service_type', $serviceType->value)
            ->whereBelongsTo($vehicleType)
            ->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($rule === null) {
            throw ValidationException::withMessages([
                'vehicle_type_id' => ['Không có quy tắc giá đang hoạt động cho dịch vụ và loại xe này.'],
            ]);
        }

        return $rule;
    }

    public function calculate(
        PricingRule $rule,
        float $distanceMeters,
        float $voucherDiscount = 0,
        float $surchargeAmount = 0,
    ): PricingBreakdown {
        $distanceKm = max(0, $distanceMeters) / 1_000;
        $extraDistanceKm = max(0, $distanceKm - $rule->base_distance_km);
        $baseFare = $this->roundCurrency($rule->base_fare);
        $extraDistanceFare = $this->roundCurrency(
            $extraDistanceKm * $rule->price_per_extra_km,
        );
        $surchargeAmount = $this->roundCurrency(max(0, $surchargeAmount));
        $grossFare = $this->roundCurrency(
            $baseFare + $extraDistanceFare + $surchargeAmount,
        );
        $voucherDiscount = min(
            $grossFare,
            $this->roundCurrency(max(0, $voucherDiscount)),
        );

        return new PricingBreakdown(
            baseFare: $baseFare,
            extraDistanceFare: $extraDistanceFare,
            surchargeAmount: $surchargeAmount,
            grossFare: $grossFare,
            voucherDiscount: $voucherDiscount,
            customerPayable: max(0, $grossFare - $voucherDiscount),
            driverRate: $rule->driver_rate,
            currency: $rule->currency,
        );
    }

    public function roundCurrency(float $amount): float
    {
        $setting = SystemSetting::query()
            ->where('key', 'pricing.rounding_unit')
            ->first();
        $configuredUnit = $setting !== null
            ? $setting->value
            : config('pricing.rounding_unit', 1_000);
        $unit = is_numeric($configuredUnit) ? (float) $configuredUnit : 1_000;
        $unit = $unit > 0 ? $unit : 1;

        return round($amount / $unit) * $unit;
    }
}
