<?php

namespace App\Models;

use Database\Factories\VoucherRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'voucher_id',
    'user_id',
    'service_request_id',
    'status',
    'discount_amount',
    'used_at',
    'restored_at',
    'restore_reason_code',
    'restore_count',
])]
class VoucherRedemption extends Model
{
    /** @use HasFactory<VoucherRedemptionFactory> */
    use HasFactory;

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discount_amount' => 'float',
            'used_at' => 'immutable_datetime',
            'restored_at' => 'immutable_datetime',
            'restore_count' => 'integer',
        ];
    }
}
