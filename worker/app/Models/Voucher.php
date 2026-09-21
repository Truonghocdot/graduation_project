<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property DiscountType $discount_type
 * @property float $discount_value
 * @property float|null $max_discount_amount
 * @property ServiceType|null $service_scope
 * @property float $minimum_order_amount
 * @property int|null $total_usage_limit
 * @property int|null $per_user_usage_limit
 * @property int $used_count
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $is_active
 */
#[Fillable([
    'code',
    'name',
    'discount_type',
    'discount_value',
    'max_discount_amount',
    'service_scope',
    'minimum_order_amount',
    'total_usage_limit',
    'per_user_usage_limit',
    'max_restore_count',
    'used_count',
    'starts_at',
    'ends_at',
    'is_active',
    'created_by',
])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Voucher $voucher): void {
            $voucher->public_id ??= (string) Str::uuid();
            $voucher->code = mb_strtoupper($voucher->code);
        });

        static::updating(function (Voucher $voucher): void {
            $voucher->code = mb_strtoupper($voucher->code);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'float',
            'max_discount_amount' => 'float',
            'service_scope' => ServiceType::class,
            'minimum_order_amount' => 'float',
            'total_usage_limit' => 'integer',
            'per_user_usage_limit' => 'integer',
            'max_restore_count' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }
}
