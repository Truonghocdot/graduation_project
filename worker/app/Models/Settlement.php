<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Database\Factories\SettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $payment_id
 * @property int $assignment_id
 * @property int $driver_profile_id
 * @property SettlementStatus $status
 * @property float $driver_rate
 * @property float $driver_gross_earning
 * @property float $cash_collected
 * @property float $wallet_payment_amount
 * @property float $voucher_payment_amount
 * @property float $platform_fee_debited
 * @property float $settlement_adjustment
 * @property float $driver_net_earning
 */
#[Fillable([
    'payment_id', 'assignment_id', 'driver_profile_id', 'status', 'driver_rate',
    'driver_gross_earning', 'cash_collected', 'wallet_payment_amount',
    'voucher_payment_amount', 'platform_fee_debited', 'settlement_adjustment',
    'driver_net_earning', 'earning_ledger_id', 'platform_fee_ledger_id',
    'settled_at', 'failure_code',
])]
class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Settlement $settlement): void {
            $settlement->public_id ??= (string) Str::uuid();
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

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<DriverProfile, $this> */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'driver_rate' => 'float',
            'driver_gross_earning' => 'float',
            'cash_collected' => 'float',
            'wallet_payment_amount' => 'float',
            'voucher_payment_amount' => 'float',
            'platform_fee_debited' => 'float',
            'settlement_adjustment' => 'float',
            'driver_net_earning' => 'float',
            'settled_at' => 'immutable_datetime',
        ];
    }
}
