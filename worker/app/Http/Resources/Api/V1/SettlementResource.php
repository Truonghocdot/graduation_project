<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Settlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Settlement */
class SettlementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'driver_rate' => $this->driver_rate,
            'driver_gross_earning' => $this->driver_gross_earning,
            'cash_collected' => $this->cash_collected,
            'wallet_payment_amount' => $this->wallet_payment_amount,
            'voucher_payment_amount' => $this->voucher_payment_amount,
            'platform_fee_debited' => $this->platform_fee_debited,
            'settlement_adjustment' => $this->settlement_adjustment,
            'driver_net_earning' => $this->driver_net_earning,
            'settled_at' => $this->settled_at,
            'failure_code' => $this->failure_code,
        ];
    }
}
