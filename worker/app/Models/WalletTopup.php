<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WalletTopupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $wallet_id
 * @property float $amount
 * @property string $status
 * @property string $vietqr_reference
 * @property string $vietqr_payload
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'wallet_id', 'amount', 'status', 'vietqr_reference', 'vietqr_payload',
    'sepay_transaction_id', 'provider_payload', 'ledger_transaction_id',
    'expires_at', 'completed_at',
])]
class WalletTopup extends Model
{
    /** @use HasFactory<WalletTopupFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (WalletTopup $topup): void {
            $topup->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'provider_payload' => 'array',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
