<?php

namespace App\Models;

use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $payment_id
 * @property float $amount
 * @property string $method
 * @property string $status
 */
#[Fillable([
    'payment_id', 'amount', 'method', 'status', 'reason_code',
    'requested_by', 'approved_by', 'ledger_transaction_id',
    'evidence', 'completed_at',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Refund $refund): void {
            $refund->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'evidence' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
