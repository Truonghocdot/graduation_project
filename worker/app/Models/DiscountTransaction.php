<?php

namespace App\Models;

use Database\Factories\DiscountTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'payment_id',
    'voucher_redemption_id',
    'amount',
    'status',
    'applied_at',
    'restored_at',
])]
class DiscountTransaction extends Model
{
    /** @use HasFactory<DiscountTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (DiscountTransaction $transaction): void {
            $transaction->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<VoucherRedemption, $this> */
    public function voucherRedemption(): BelongsTo
    {
        return $this->belongsTo(VoucherRedemption::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'applied_at' => 'immutable_datetime',
            'restored_at' => 'immutable_datetime',
        ];
    }
}
