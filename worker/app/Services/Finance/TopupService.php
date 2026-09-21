<?php

namespace App\Services\Finance;

use App\Models\LedgerAccount;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WebhookReceipt;
use App\Services\Booking\IdempotencyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TopupService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly LedgerService $ledger,
    ) {}

    public function create(User $user, float $amount, string $idempotencyKey): WalletTopup
    {
        return DB::transaction(function () use ($user, $amount, $idempotencyKey): WalletTopup {
            $idempotency = $this->idempotency->begin(
                $user,
                'wallet-topup.create',
                $idempotencyKey,
                ['amount' => $amount],
            );

            if ($idempotency->status === 'COMPLETED') {
                $replayed = WalletTopup::query()->findOrFail($idempotency->resource_id);
                $replayed->wasRecentlyCreated = true;

                return $replayed;
            }

            $wallet = $this->ensureWallet($user);
            $reference = 'TOPUP'.mb_strtoupper(Str::random(12));
            $payload = http_build_query([
                'bank' => config('finance.vietqr.bank_code'),
                'account' => config('finance.vietqr.account_number'),
                'account_name' => config('finance.vietqr.account_name'),
                'amount' => $amount,
                'content' => $reference,
            ]);
            $topup = WalletTopup::query()->create([
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'status' => 'PENDING',
                'vietqr_reference' => $reference,
                'vietqr_payload' => $payload,
                'expires_at' => now()->addMinutes((int) config('finance.topup_ttl_minutes', 30)),
            ]);
            $this->idempotency->complete(
                $idempotency,
                201,
                ['id' => $topup->public_id],
                WalletTopup::class,
                $topup->id,
            );

            return $topup;
        });
    }

    /** @param array<string, mixed> $providerPayload */
    public function complete(
        string $providerEventId,
        string $transactionId,
        string $reference,
        float $amount,
        array $providerPayload,
    ): WalletTopup {
        return DB::transaction(function () use (
            $providerEventId,
            $transactionId,
            $reference,
            $amount,
            $providerPayload,
        ): WalletTopup {
            $receipt = WebhookReceipt::query()->firstOrCreate(
                ['provider' => 'SEPAY', 'provider_event_id' => $providerEventId],
                [
                    'signature_valid' => true,
                    'payload' => $providerPayload,
                    'status' => 'PROCESSING',
                    'created_at' => now(),
                ],
            );

            if (! $receipt->wasRecentlyCreated && $receipt->status === 'PROCESSED') {
                return WalletTopup::query()
                    ->where('sepay_transaction_id', $transactionId)
                    ->firstOrFail();
            }

            $topup = WalletTopup::query()
                ->where('vietqr_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($topup === null
                || $topup->status !== 'PENDING'
                || $topup->expires_at->isPast()
                || abs($topup->amount - $amount) > 0.01) {
                throw ValidationException::withMessages([
                    'webhook' => ['The top-up reference, status, expiry, or amount is invalid.'],
                ]);
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($topup->wallet_id);
            $wallet->balance += $amount;
            $wallet->version++;
            $wallet->save();
            $bank = $this->ledger->systemAccount(
                'SYSTEM:BANK_CLEARING:'.$wallet->currency,
                'CLEARING',
                $wallet->currency,
            );
            $ledger = $this->ledger->post(
                'TOP_UP',
                'TOP_UP',
                $topup->id,
                'sepay:'.$transactionId,
                [
                    ['account' => $bank, 'direction' => 'DEBIT', 'amount' => $amount],
                    [
                        'account' => $wallet->ledgerAccount,
                        'direction' => 'CREDIT',
                        'amount' => $amount,
                        'balance_after' => $wallet->balance,
                    ],
                ],
                ['sepay_transaction_id' => $transactionId],
            );
            $topup->forceFill([
                'status' => 'COMPLETED',
                'sepay_transaction_id' => $transactionId,
                'provider_payload' => $providerPayload,
                'ledger_transaction_id' => $ledger->id,
                'completed_at' => now(),
            ])->save();
            $receipt->forceFill([
                'status' => 'PROCESSED',
                'processed_at' => now(),
            ])->save();
            OutboxEvent::query()->create([
                'event_type' => 'WALLET_TOPPED_UP',
                'aggregate_type' => 'WALLET',
                'aggregate_id' => $wallet->id,
                'aggregate_version' => $wallet->version,
                'payload' => [
                    'wallet_id' => $wallet->public_id,
                    'topup_id' => $topup->public_id,
                    'amount' => $amount,
                    'balance' => $wallet->balance,
                ],
                'status' => 'PENDING',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);

            return $topup->fresh();
        });
    }

    public function ensureWallet(User $user): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('currency', 'VND')
            ->first();

        if ($wallet !== null) {
            return $wallet;
        }

        $account = LedgerAccount::query()->firstOrCreate(
            ['code' => 'USER:'.$user->id.':VND'],
            [
                'owner_type' => 'USER',
                'owner_user_id' => $user->id,
                'account_type' => 'WALLET_LIABILITY',
                'currency' => 'VND',
                'status' => 'ACTIVE',
            ],
        );

        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id, 'currency' => 'VND'],
            [
                'ledger_account_id' => $account->id,
                'balance' => 0,
                'reserved_withdrawal_amount' => 0,
                'status' => 'ACTIVE',
                'version' => 1,
            ],
        );
    }
}
