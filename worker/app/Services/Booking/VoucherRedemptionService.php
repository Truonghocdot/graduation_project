<?php

namespace App\Services\Booking;

use App\Enums\DiscountType;
use App\Models\DiscountTransaction;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\Pricing\PricingService;
use Illuminate\Validation\ValidationException;

class VoucherRedemptionService
{
    public function __construct(private readonly PricingService $pricingService) {}

    public function redeem(
        User $user,
        Quote $quote,
        ServiceRequest $serviceRequest,
        Payment $payment,
    ): ?VoucherRedemption {
        $voucherSnapshot = data_get($quote->service_payload, 'voucher');

        if ($voucherSnapshot === null) {
            if ($quote->voucher_discount > 0) {
                throw ValidationException::withMessages([
                    'voucher_code' => ['The quote voucher snapshot is invalid.'],
                ]);
            }

            return null;
        }

        $voucherId = data_get($voucherSnapshot, 'id');
        $voucher = Voucher::query()
            ->where('public_id', $voucherId)
            ->lockForUpdate()
            ->first();

        if ($voucher === null || ! $voucher->is_active || $voucher->starts_at->isFuture() || $voucher->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'voucher_code' => ['The voucher is invalid or expired.'],
            ]);
        }

        if ($voucher->service_scope !== null && $voucher->service_scope !== $quote->service_type) {
            throw ValidationException::withMessages([
                'voucher_code' => ['The voucher does not apply to this service.'],
            ]);
        }

        if ($quote->gross_fare < $voucher->minimum_order_amount) {
            throw ValidationException::withMessages([
                'voucher_code' => ['The quote no longer meets the voucher minimum amount.'],
            ]);
        }

        if ($voucher->total_usage_limit !== null && $voucher->used_count >= $voucher->total_usage_limit) {
            throw ValidationException::withMessages([
                'voucher_code' => ['The voucher usage limit has been reached.'],
            ]);
        }

        if ($voucher->per_user_usage_limit !== null) {
            $usageCount = VoucherRedemption::query()
                ->where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->where('status', 'USED')
                ->count();

            if ($usageCount >= $voucher->per_user_usage_limit) {
                throw ValidationException::withMessages([
                    'voucher_code' => ['You have reached the voucher usage limit.'],
                ]);
            }
        }

        $discount = $voucher->discount_type === DiscountType::Percent
            ? $quote->gross_fare * $voucher->discount_value / 100
            : $voucher->discount_value;
        $discount = min($quote->gross_fare, $discount);

        if ($voucher->max_discount_amount !== null) {
            $discount = min($discount, $voucher->max_discount_amount);
        }

        $discount = min($quote->gross_fare, $this->pricingService->roundCurrency($discount));

        if (abs($discount - $quote->voucher_discount) > 0.01) {
            throw ValidationException::withMessages([
                'voucher_code' => ['The quote voucher amount is no longer valid.'],
            ]);
        }

        $redemption = VoucherRedemption::query()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'service_request_id' => $serviceRequest->id,
            'status' => 'USED',
            'discount_amount' => $quote->voucher_discount,
            'used_at' => now(),
            'restore_count' => 0,
        ]);

        DiscountTransaction::query()->create([
            'payment_id' => $payment->id,
            'voucher_redemption_id' => $redemption->id,
            'amount' => $quote->voucher_discount,
            'status' => 'APPLIED',
            'applied_at' => now(),
        ]);

        $voucher->increment('used_count');

        return $redemption;
    }

    public function restore(Payment $payment, string $reasonCode): void
    {
        $discountTransaction = DiscountTransaction::query()
            ->where('payment_id', $payment->id)
            ->lockForUpdate()
            ->first();

        if ($discountTransaction === null || $discountTransaction->status === 'RESTORED') {
            return;
        }

        $redemption = VoucherRedemption::query()
            ->lockForUpdate()
            ->findOrFail($discountTransaction->voucher_redemption_id);
        $voucher = Voucher::query()->lockForUpdate()->findOrFail($redemption->voucher_id);

        $canRestore = $voucher->max_restore_count === null
            || $redemption->restore_count < $voucher->max_restore_count;

        if ($redemption->status === 'USED' && $canRestore) {
            $redemption->forceFill([
                'status' => 'RESTORED',
                'restored_at' => now(),
                'restore_reason_code' => $reasonCode,
                'restore_count' => $redemption->restore_count + 1,
            ])->save();
            $voucher->decrement('used_count');
        }

        $discountTransaction->forceFill([
            'status' => $canRestore ? 'RESTORED' : 'NOT_RESTORABLE',
            'restored_at' => $canRestore ? now() : null,
        ])->save();
    }
}
