<?php

namespace App\Models;

use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property PayerType $payer_type
 * @property string $currency
 * @property float $customer_payable
 * @property int|null $payer_user_id
 * @property int|null $customer_payment_ledger_id
 * @property int $version
 */
#[Fillable([
    'service_request_id',
    'payer_type',
    'payer_user_id',
    'method',
    'status',
    'currency',
    'gross_fare',
    'voucher_discount',
    'customer_payable',
    'customer_payment_ledger_id',
    'cash_collected',
    'cash_confirmed_by',
    'cash_confirmed_at',
    'version',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function customerPaymentLedger(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'customer_payment_ledger_id');
    }

    /** @return HasOne<DiscountTransaction, $this> */
    public function discountTransaction(): HasOne
    {
        return $this->hasOne(DiscountTransaction::class);
    }

    /** @return HasOne<Settlement, $this> */
    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payer_type' => PayerType::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'gross_fare' => 'float',
            'voucher_discount' => 'float',
            'customer_payable' => 'float',
            'cash_collected' => 'float',
            'cash_confirmed_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
