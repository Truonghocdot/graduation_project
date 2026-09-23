<?php

namespace App\Services\Finance;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleKey;
use App\Models\AuditLog;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @param array<string, mixed>|null $evidence */
    public function refund(
        Payment $payment,
        User $admin,
        float $amount,
        string $reasonCode,
        ?array $evidence = null,
    ): Refund {
        return DB::transaction(function () use (
            $payment,
            $admin,
            $amount,
            $reasonCode,
            $evidence,
        ): Refund {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $alreadyRefunded = (float) Refund::query()
                ->where('payment_id', $payment->id)
                ->where('status', 'COMPLETED')
                ->sum('amount');
            $remaining = max(0, $payment->customer_payable - $alreadyRefunded);

            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => ['Số tiền hoàn vượt quá số tiền khách hàng đã thanh toán còn lại.'],
                ]);
            }

            if ($payment->method === PaymentMethod::Cash && empty($evidence)) {
                throw ValidationException::withMessages([
                    'evidence' => ['Hoàn tiền mặt cần có bằng chứng thủ công.'],
                ]);
            }

            $refund = Refund::query()->create([
                'payment_id' => $payment->id,
                'amount' => $amount,
                'method' => $payment->method === PaymentMethod::Wallet
                    ? 'WALLET'
                    : 'CASH_MANUAL',
                'status' => 'PENDING',
                'reason_code' => $reasonCode,
                'requested_by' => $admin->id,
                'approved_by' => $admin->id,
                'evidence' => $evidence,
            ]);
            $ledgerId = null;

            if ($payment->method === PaymentMethod::Wallet) {
                $wallet = Wallet::query()
                    ->where('user_id', $payment->payer_user_id)
                    ->where('currency', $payment->currency)
                    ->lockForUpdate()
                    ->firstOrFail();
                $wallet->balance += $amount;
                $wallet->version++;
                $wallet->save();
                $clearing = $this->ledger->systemAccount(
                    'SYSTEM:REFUND_CLEARING:'.$payment->currency,
                    'CLEARING',
                    $payment->currency,
                );
                $ledger = $this->ledger->post(
                    'REFUND',
                    'REFUND',
                    $refund->id,
                    'refund:'.$refund->id,
                    [
                        ['account' => $clearing, 'direction' => 'DEBIT', 'amount' => $amount],
                        [
                            'account' => $wallet->ledgerAccount,
                            'direction' => 'CREDIT',
                            'amount' => $amount,
                            'balance_after' => $wallet->balance,
                        ],
                    ],
                );
                $ledgerId = $ledger->id;
            }

            $refund->forceFill([
                'status' => 'COMPLETED',
                'ledger_transaction_id' => $ledgerId,
                'completed_at' => now(),
            ])->save();
            $totalRefunded = $alreadyRefunded + $amount;
            $payment->forceFill([
                'status' => abs($totalRefunded - $payment->customer_payable) <= 0.01
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
                'version' => $payment->version + 1,
            ])->save();
            AuditLog::query()->create([
                'actor_user_id' => $admin->id,
                'actor_role' => RoleKey::Admin->value,
                'action' => 'REFUND_COMPLETED',
                'subject_type' => Payment::class,
                'subject_id' => $payment->id,
                'before' => ['status' => PaymentStatus::Settled->value],
                'after' => [
                    'status' => $payment->status->value,
                    'refund_id' => $refund->public_id,
                    'amount' => $amount,
                ],
                'reason_code' => $reasonCode,
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
            OutboxEvent::query()->create([
                'event_type' => 'REFUND_COMPLETED',
                'aggregate_type' => 'PAYMENT',
                'aggregate_id' => $payment->id,
                'aggregate_version' => $payment->version,
                'payload' => [
                    'service_request_id' => $payment->serviceRequest->public_id,
                    'payment_id' => $payment->public_id,
                    'refund_id' => $refund->public_id,
                    'amount' => $amount,
                    'status' => $payment->status->value,
                ],
                'status' => 'PENDING',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);

            return $refund->load('payment');
        });
    }
}
