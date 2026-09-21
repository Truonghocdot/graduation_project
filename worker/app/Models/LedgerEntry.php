<?php

namespace App\Models;

use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ledger_transaction_id',
    'ledger_account_id',
    'direction',
    'amount',
    'balance_after',
    'created_at',
])]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'balance_after' => 'float',
            'created_at' => 'immutable_datetime',
        ];
    }
}
