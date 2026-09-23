<?php

namespace App\Services\Finance;

use App\Enums\RoleKey;
use App\Models\AuditLog;
use App\Models\DriverBankAccount;
use App\Models\DriverProfile;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Services\Booking\IdempotencyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly LedgerService $ledger,
    ) {}

    public function request(
        User $driver,
        string $bankAccountPublicId,
        float $amount,
        string $idempotencyKey,
    ): WithdrawalRequest {
        return DB::transaction(function () use (
            $driver,
            $bankAccountPublicId,
            $amount,
            $idempotencyKey,
        ): WithdrawalRequest {
            $idempotency = $this->idempotency->begin(
                $driver,
                'withdrawal.create',
                $idempotencyKey,
                ['bank_account_id' => $bankAccountPublicId, 'amount' => $amount],
            );

            if ($idempotency->status === 'COMPLETED') {
                $replayed = WithdrawalRequest::query()->findOrFail($idempotency->resource_id);
                $replayed->wasRecentlyCreated = true;

                return $replayed;
            }

            $profile = DriverProfile::query()->where('user_id', $driver->id)->firstOrFail();
            $bankAccount = DriverBankAccount::query()
                ->where('public_id', $bankAccountPublicId)
                ->where('driver_profile_id', $profile->id)
                ->where('is_verified', true)
                ->firstOrFail();
            $wallet = Wallet::query()
                ->where('user_id', $driver->id)
                ->where('currency', 'VND')
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->firstOrFail();
            $minimum = (float) config('finance.withdrawal_min_amount', 50_000);
            $maximum = (float) config('finance.withdrawal_max_amount', 50_000_000);

            if ($amount < $minimum || $amount > $maximum) {
                throw ValidationException::withMessages([
                    'amount' => ['Số tiền rút nằm ngoài hạn mức đã cấu hình.'],
                ]);
            }

            if ($wallet->balance < 0
                || $wallet->balance - $wallet->reserved_withdrawal_amount < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Ví không có đủ số dư khả dụng.'],
                ]);
            }

            $wallet->reserved_withdrawal_amount += $amount;
            $wallet->version++;
            $wallet->save();
            $withdrawal = WithdrawalRequest::query()->create([
                'wallet_id' => $wallet->id,
                'driver_bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'status' => 'PENDING',
                'requested_at' => now(),
            ]);
            $this->idempotency->complete(
                $idempotency,
                201,
                ['id' => $withdrawal->public_id],
                WithdrawalRequest::class,
                $withdrawal->id,
            );
            $this->outbox($withdrawal, 'DRIVER_WITHDRAWAL_REQUESTED');

            return $withdrawal->load(['wallet', 'bankAccount']);
        });
    }

    public function complete(
        WithdrawalRequest $withdrawal,
        User $admin,
        string $bankTransferReference,
    ): WithdrawalRequest {
        return DB::transaction(function () use (
            $withdrawal,
            $admin,
            $bankTransferReference,
        ): WithdrawalRequest {
            $request = WithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if ($request->status === 'COMPLETED') {
                return $request;
            }

            if (! in_array($request->status, ['PENDING', 'APPROVED'], true)) {
                $this->invalidState();
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($request->wallet_id);

            if ($wallet->reserved_withdrawal_amount < $request->amount
                || $wallet->balance < $request->amount) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Khoản giữ chỗ trong ví không còn hợp lệ.'],
                ]);
            }

            $before = $request->attributesToArray();
            $wallet->balance -= $request->amount;
            $wallet->reserved_withdrawal_amount -= $request->amount;
            $wallet->version++;
            $wallet->save();
            $bank = $this->ledger->systemAccount(
                'SYSTEM:BANK_CLEARING:'.$wallet->currency,
                'CLEARING',
                $wallet->currency,
            );
            $ledger = $this->ledger->post(
                'WITHDRAWAL',
                'WITHDRAWAL',
                $request->id,
                'withdrawal:'.$request->id,
                [
                    [
                        'account' => $wallet->ledgerAccount,
                        'direction' => 'DEBIT',
                        'amount' => $request->amount,
                        'balance_after' => $wallet->balance,
                    ],
                    ['account' => $bank, 'direction' => 'CREDIT', 'amount' => $request->amount],
                ],
                ['bank_transfer_reference' => $bankTransferReference],
            );
            $request->forceFill([
                'status' => 'COMPLETED',
                'handled_by' => $admin->id,
                'handled_at' => now(),
                'bank_transfer_reference' => $bankTransferReference,
                'ledger_transaction_id' => $ledger->id,
            ])->save();
            $this->audit($request, $admin, 'WITHDRAWAL_COMPLETED', $before);
            $this->outbox($request, 'DRIVER_WITHDRAWAL_COMPLETED');

            return $request->load(['wallet', 'bankAccount']);
        });
    }

    public function reject(
        WithdrawalRequest $withdrawal,
        User $admin,
        string $reasonCode,
    ): WithdrawalRequest {
        return DB::transaction(function () use ($withdrawal, $admin, $reasonCode): WithdrawalRequest {
            $request = WithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if ($request->status === 'REJECTED') {
                return $request;
            }

            if (! in_array($request->status, ['PENDING', 'APPROVED'], true)) {
                $this->invalidState();
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($request->wallet_id);
            $before = $request->attributesToArray();
            $wallet->reserved_withdrawal_amount = max(
                0,
                $wallet->reserved_withdrawal_amount - $request->amount,
            );
            $wallet->version++;
            $wallet->save();
            $request->forceFill([
                'status' => 'REJECTED',
                'handled_by' => $admin->id,
                'handled_at' => now(),
                'reason_code' => $reasonCode,
            ])->save();
            $this->audit($request, $admin, 'WITHDRAWAL_REJECTED', $before);
            $this->outbox($request, 'DRIVER_WITHDRAWAL_FAILED');

            return $request->load(['wallet', 'bankAccount']);
        });
    }

    private function invalidState(): never
    {
        throw ValidationException::withMessages([
            'withdrawal' => ['Không thể xử lý yêu cầu rút tiền ở trạng thái hiện tại.'],
        ]);
    }

    private function outbox(WithdrawalRequest $request, string $eventType): void
    {
        OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => 'WITHDRAWAL',
            'aggregate_id' => $request->id,
            'aggregate_version' => null,
            'payload' => [
                'withdrawal_id' => $request->public_id,
                'status' => $request->status,
                'amount' => $request->amount,
            ],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $before */
    private function audit(
        WithdrawalRequest $request,
        User $admin,
        string $action,
        array $before,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_role' => RoleKey::Admin->value,
            'action' => $action,
            'subject_type' => WithdrawalRequest::class,
            'subject_id' => $request->id,
            'before' => $before,
            'after' => $request->attributesToArray(),
            'reason_code' => $request->reason_code,
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
