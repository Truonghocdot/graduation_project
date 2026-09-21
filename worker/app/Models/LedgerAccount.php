<?php

namespace App\Models;

use Database\Factories\LedgerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'owner_type',
    'owner_user_id',
    'code',
    'account_type',
    'currency',
    'status',
])]
class LedgerAccount extends Model
{
    /** @use HasFactory<LedgerAccountFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (LedgerAccount $account): void {
            $account->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasOne<Wallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }
}
