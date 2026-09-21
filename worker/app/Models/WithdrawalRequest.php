<?php

namespace App\Models;

use Database\Factories\WithdrawalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $wallet_id
 * @property int $driver_bank_account_id
 * @property float $amount
 * @property string $status
 */
#[Fillable([
    'wallet_id', 'driver_bank_account_id', 'amount', 'status', 'requested_at',
    'handled_by', 'handled_at', 'bank_transfer_reference',
    'ledger_transaction_id', 'reason_code',
])]
class WithdrawalRequest extends Model
{
    /** @use HasFactory<WithdrawalRequestFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (WithdrawalRequest $withdrawal): void {
            $withdrawal->public_id ??= (string) Str::uuid();
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

    /** @return BelongsTo<DriverBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(DriverBankAccount::class, 'driver_bank_account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'requested_at' => 'immutable_datetime',
            'handled_at' => 'immutable_datetime',
        ];
    }
}
