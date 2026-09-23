<?php

namespace App\Services\Booking;

use App\Enums\PaymentStatus;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletPaymentService
{
    public function debit(
        Payment $payment,
        int $payerUserId,
        float $amount,
        string $idempotencyKey,
    ): void {
        if ($amount <= 0) {
            $payment->forceFill(['status' => PaymentStatus::Ready])->save();

            return;
        }

        $wallet = Wallet::query()
            ->where('user_id', $payerUserId)
            ->where('currency', $payment->currency)
            ->where('status', 'ACTIVE')
            ->lockForUpdate()
            ->first();

        if ($wallet === null) {
            throw ValidationException::withMessages([
                'payment_method' => ['Người thanh toán chưa có ví đang hoạt động.'],
            ]);
        }

        $availableBalance = $wallet->balance - $wallet->reserved_withdrawal_amount;

        if ($availableBalance < $amount) {
            throw ValidationException::withMessages([
                'payment_method' => ['Số dư khả dụng trong ví của người thanh toán không đủ.'],
            ]);
        }

        $wallet->balance -= $amount;
        $wallet->version++;
        $wallet->save();

        $ledgerTransaction = LedgerTransaction::query()->create([
            'transaction_type' => 'CUSTOMER_PAYMENT',
            'status' => 'POSTED',
            'reference_type' => 'PAYMENT',
            'reference_id' => $payment->id,
            'idempotency_key' => 'payment:'.$payment->id.':'.$idempotencyKey,
            'correlation_id' => (string) Str::uuid(),
            'metadata' => [
                'payment_id' => $payment->public_id,
                'payer_user_id' => $payerUserId,
            ],
            'posted_at' => now(),
        ]);

        LedgerEntry::query()->create([
            'ledger_transaction_id' => $ledgerTransaction->id,
            'ledger_account_id' => $wallet->ledger_account_id,
            'direction' => 'DEBIT',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'created_at' => now(),
        ]);
        LedgerEntry::query()->create([
            'ledger_transaction_id' => $ledgerTransaction->id,
            'ledger_account_id' => $this->clearingAccount($payment->currency)->id,
            'direction' => 'CREDIT',
            'amount' => $amount,
            'balance_after' => null,
            'created_at' => now(),
        ]);

        $payment->forceFill([
            'customer_payment_ledger_id' => $ledgerTransaction->id,
            'status' => PaymentStatus::Ready,
        ])->save();
    }

    public function refund(
        Payment $payment,
        int $payerUserId,
        float $amount,
        string $idempotencyKey,
    ): void {
        if ($amount <= 0 || $payment->customer_payment_ledger_id === null) {
            return;
        }

        $wallet = Wallet::query()
            ->where('user_id', $payerUserId)
            ->where('currency', $payment->currency)
            ->where('status', 'ACTIVE')
            ->lockForUpdate()
            ->firstOrFail();

        $wallet->balance += $amount;
        $wallet->version++;
        $wallet->save();

        $ledgerTransaction = LedgerTransaction::query()->create([
            'transaction_type' => 'CUSTOMER_PAYMENT_REFUND',
            'status' => 'POSTED',
            'reference_type' => 'PAYMENT',
            'reference_id' => $payment->id,
            'idempotency_key' => 'payment:'.$payment->id.':refund:'.$idempotencyKey,
            'correlation_id' => (string) Str::uuid(),
            'metadata' => [
                'payment_id' => $payment->public_id,
                'payer_user_id' => $payerUserId,
            ],
            'posted_at' => now(),
        ]);

        LedgerEntry::query()->create([
            'ledger_transaction_id' => $ledgerTransaction->id,
            'ledger_account_id' => $wallet->ledger_account_id,
            'direction' => 'CREDIT',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'created_at' => now(),
        ]);
        LedgerEntry::query()->create([
            'ledger_transaction_id' => $ledgerTransaction->id,
            'ledger_account_id' => $this->clearingAccount($payment->currency)->id,
            'direction' => 'DEBIT',
            'amount' => $amount,
            'balance_after' => null,
            'created_at' => now(),
        ]);
    }

    private function clearingAccount(string $currency): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => 'SYSTEM:CUSTOMER_PAYMENT:'.$currency],
            [
                'owner_type' => 'SYSTEM',
                'owner_user_id' => null,
                'account_type' => 'PAYMENT_CLEARING',
                'currency' => $currency,
                'status' => 'ACTIVE',
            ],
        );
    }
}
