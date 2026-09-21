<?php

namespace App\Services\Finance;

use App\Enums\RoleKey;
use App\Models\AuditLog;
use App\Models\DriverBankAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankAccountReviewService
{
    public function verify(DriverBankAccount $account, User $admin): DriverBankAccount
    {
        return DB::transaction(function () use ($account, $admin): DriverBankAccount {
            $account = DriverBankAccount::query()->lockForUpdate()->findOrFail($account->id);
            $before = ['is_verified' => $account->is_verified, 'is_default' => $account->is_default];

            if (! $account->driverProfile->bankAccounts()->where('is_default', true)->exists()) {
                $account->is_default = true;
            }
            $account->is_verified = true;
            $account->save();
            AuditLog::query()->create([
                'actor_user_id' => $admin->id,
                'actor_role' => RoleKey::Admin->value,
                'action' => 'DRIVER_BANK_ACCOUNT_VERIFIED',
                'subject_type' => DriverBankAccount::class,
                'subject_id' => $account->id,
                'before' => $before,
                'after' => [
                    'is_verified' => $account->is_verified,
                    'is_default' => $account->is_default,
                ],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $account;
        });
    }
}
