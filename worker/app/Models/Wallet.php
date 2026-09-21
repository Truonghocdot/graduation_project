<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property float $balance
 */
#[Fillable([
    'user_id',
    'ledger_account_id',
    'currency',
    'balance',
    'reserved_withdrawal_amount',
    'status',
    'version',
])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Wallet $wallet): void {
            $wallet->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'balance' => 'float',
            'reserved_withdrawal_amount' => 'float',
        ];
    }
}
