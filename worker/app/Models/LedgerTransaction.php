<?php

namespace App\Models;

use Database\Factories\LedgerTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'transaction_type',
    'status',
    'reference_type',
    'reference_id',
    'idempotency_key',
    'correlation_id',
    'metadata',
    'posted_at',
])]
class LedgerTransaction extends Model
{
    /** @use HasFactory<LedgerTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (LedgerTransaction $transaction): void {
            $transaction->public_id ??= (string) Str::uuid();
            $transaction->correlation_id ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'posted_at' => 'immutable_datetime',
        ];
    }
}
