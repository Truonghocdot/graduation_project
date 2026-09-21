<?php

namespace App\Models;

use Database\Factories\CodTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cod_account_id', 'transaction_type', 'amount', 'actor_user_id',
    'evidence', 'idempotency_key', 'occurred_at', 'created_at',
])]
class CodTransaction extends Model
{
    /** @use HasFactory<CodTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<CodAccount, $this> */
    public function codAccount(): BelongsTo
    {
        return $this->belongsTo(CodAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'evidence' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
