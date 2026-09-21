<?php

namespace App\Http\Resources\Api\V1;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WithdrawalRequest */
class WithdrawalRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => [
                'id' => $this->bankAccount->public_id,
                'bank_code' => $this->bankAccount->bank_code,
                'account_name' => $this->bankAccount->account_name,
            ]),
            'requested_at' => $this->requested_at,
            'handled_at' => $this->handled_at,
            'reason_code' => $this->reason_code,
        ];
    }
}
