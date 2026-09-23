<?php

namespace App\Services\Pricing;

use App\Enums\DiscountType;
use App\Enums\ServiceType;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherPreviewService
{
    /**
     * @return array{discount: float, voucher: Voucher|null}
     */
    public function preview(
        ?string $code,
        User $user,
        ServiceType $serviceType,
        float $grossFare,
    ): array {
        if ($code === null || trim($code) === '') {
            return ['discount' => 0, 'voucher' => null];
        }

        $voucher = Voucher::query()
            ->where('code', mb_strtoupper(trim($code)))
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->first();

        if ($voucher === null) {
            $this->invalidVoucher('Mã giảm giá không hợp lệ hoặc đã hết hạn.');
        }

        if ($voucher->service_scope !== null && $voucher->service_scope !== $serviceType) {
            $this->invalidVoucher('Mã giảm giá không áp dụng cho dịch vụ này.');
        }

        if ($grossFare < $voucher->minimum_order_amount) {
            $this->invalidVoucher('Báo giá không đáp ứng giá trị đơn hàng tối thiểu của mã giảm giá.');
        }

        if ($voucher->total_usage_limit !== null && $voucher->used_count >= $voucher->total_usage_limit) {
            $this->invalidVoucher('Mã giảm giá đã đạt giới hạn sử dụng.');
        }

        if ($voucher->per_user_usage_limit !== null) {
            $userUsageCount = DB::table('voucher_redemptions')
                ->where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->where('status', 'USED')
                ->count();

            if ($userUsageCount >= $voucher->per_user_usage_limit) {
                $this->invalidVoucher('Bạn đã đạt giới hạn sử dụng mã giảm giá.');
            }
        }

        $discount = $voucher->discount_type === DiscountType::Percent
            ? $grossFare * $voucher->discount_value / 100
            : $voucher->discount_value;

        if ($voucher->max_discount_amount !== null) {
            $discount = min($discount, $voucher->max_discount_amount);
        }

        return [
            'discount' => min($grossFare, max(0, $discount)),
            'voucher' => $voucher,
        ];
    }

    private function invalidVoucher(string $message): never
    {
        throw ValidationException::withMessages([
            'voucher_code' => [$message],
        ]);
    }
}
