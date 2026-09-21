<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'currency' => $this->currency,
            'balance' => $this->balance,
            'reserved_withdrawal_amount' => $this->reserved_withdrawal_amount,
            'available_balance' => $this->balance - $this->reserved_withdrawal_amount,
            'status' => $this->status,
            'version' => $this->version,
            'entries' => $this->when(
                $this->relationLoaded('ledgerAccount')
                    && $this->ledgerAccount->relationLoaded('entries'),
                fn () => $this->ledgerAccount->entries->map(fn ($entry): array => [
                    'id' => $entry->id,
                    'direction' => $entry->direction,
                    'amount' => $entry->amount,
                    'balance_after' => $entry->balance_after,
                    'created_at' => $entry->created_at,
                ])->values(),
            ),
        ];
    }
}
