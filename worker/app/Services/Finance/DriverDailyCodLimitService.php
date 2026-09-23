<?php

namespace App\Services\Finance;

use App\Models\CodTransaction;
use App\Models\DriverProfile;
use Carbon\CarbonInterface;

class DriverDailyCodLimitService
{
    public function remaining(DriverProfile $profile, ?CarbonInterface $at = null): float
    {
        return max(0, (float) $profile->cod_limit - $this->advancedToday($profile, $at));
    }

    public function canAdvance(
        DriverProfile $profile,
        float $amount,
        ?CarbonInterface $at = null,
    ): bool {
        return $amount <= $this->remaining($profile, $at);
    }

    public function advancedToday(DriverProfile $profile, ?CarbonInterface $at = null): float
    {
        $timezone = (string) config('finance.cod_business_timezone', 'Asia/Ho_Chi_Minh');
        $localTime = ($at ?? now())->setTimezone($timezone);
        $start = $localTime->copy()->startOfDay()->utc();
        $end = $localTime->copy()->endOfDay()->utc();

        return (float) CodTransaction::query()
            ->where('transaction_type', 'ADVANCE_TO_SENDER')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereHas(
                'codAccount',
                fn ($query) => $query->where('driver_profile_id', $profile->id),
            )
            ->sum('amount');
    }
}
