<?php

namespace App\Services\Driver;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverReviewStatus;
use App\Enums\ReviewableStatus;
use App\Enums\RoleKey;
use App\Models\AuditLog;
use App\Models\DriverProfile;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class DriverReviewService
{
    public function approve(DriverProfile $profile, User $admin): DriverProfile
    {
        return DB::transaction(function () use ($profile, $admin): DriverProfile {
            $profile = DriverProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $before = $this->auditSnapshot($profile);

            if ($profile->review_status !== DriverReviewStatus::PendingReview) {
                $this->throwInvalidState();
            }

            $selectedVehicle = $profile->vehicles()->where('is_selected', true)->first();

            if ($selectedVehicle === null || ! $profile->capabilities()->exists()) {
                throw ValidationException::withMessages([
                    'application' => ['Hồ sơ chưa có xe được chọn hoặc năng lực dịch vụ.'],
                ]);
            }

            if ($profile->documents()->whereDate('expires_at', '<', today())->exists()) {
                throw ValidationException::withMessages([
                    'application' => ['Cần thay thế giấy tờ hết hạn trước khi phê duyệt.'],
                ]);
            }

            $profile->documents()
                ->where('status', ReviewableStatus::Pending->value)
                ->update([
                    'status' => ReviewableStatus::Approved->value,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);
            $profile->vehicles()
                ->where('is_selected', true)
                ->update(['status' => ReviewableStatus::Approved->value]);
            $profile->capabilities()->update([
                'is_active' => true,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $profile->forceFill([
                'review_status' => DriverReviewStatus::Approved,
                'availability_status' => DriverAvailabilityStatus::Offline,
                'review_reason_code' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $this->grantDriverRole($profile->user_id, $admin->id);
            $this->ensureWallet($profile->user_id);
            $this->audit($profile, $admin, 'DRIVER_APPROVED', $before);

            return $this->load($profile);
        });
    }

    public function reject(DriverProfile $profile, User $admin, string $reasonCode): DriverProfile
    {
        return DB::transaction(function () use ($profile, $admin, $reasonCode): DriverProfile {
            $profile = DriverProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $before = $this->auditSnapshot($profile);

            if ($profile->review_status !== DriverReviewStatus::PendingReview) {
                $this->throwInvalidState();
            }

            $profile->forceFill([
                'review_status' => DriverReviewStatus::Rejected,
                'availability_status' => DriverAvailabilityStatus::Offline,
                'review_reason_code' => $reasonCode,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            $this->audit($profile, $admin, 'DRIVER_REJECTED', $before, $reasonCode);

            return $this->load($profile);
        });
    }

    public function suspend(DriverProfile $profile, User $admin, string $reasonCode): DriverProfile
    {
        return DB::transaction(function () use ($profile, $admin, $reasonCode): DriverProfile {
            $profile = DriverProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $before = $this->auditSnapshot($profile);

            if ($profile->review_status !== DriverReviewStatus::Approved) {
                $this->throwInvalidState();
            }

            $profile->forceFill([
                'review_status' => DriverReviewStatus::Suspended,
                'availability_status' => DriverAvailabilityStatus::Offline,
                'review_reason_code' => $reasonCode,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'offline_at' => now(),
            ])->save();
            $profile->capabilities()->update(['is_active' => false]);
            $this->audit($profile, $admin, 'DRIVER_SUSPENDED', $before, $reasonCode);

            return $this->load($profile);
        });
    }

    private function grantDriverRole(int $userId, int $adminId): void
    {
        $roleId = Role::query()->where('key', RoleKey::Driver->value)->value('id');

        if ($roleId === null) {
            throw new LogicException('Vai trò tài xế chưa được khởi tạo trong hệ thống.');
        }

        User::query()->findOrFail($userId)->roles()->syncWithoutDetaching([
            $roleId => ['granted_by' => $adminId, 'granted_at' => now()],
        ]);
    }

    private function ensureWallet(int $userId): void
    {
        if (Wallet::query()->where('user_id', $userId)->where('currency', 'VND')->exists()) {
            return;
        }

        $account = LedgerAccount::query()->firstOrCreate(
            ['code' => "USER:{$userId}:VND"],
            [
                'owner_type' => 'USER',
                'owner_user_id' => $userId,
                'account_type' => 'WALLET_LIABILITY',
                'currency' => 'VND',
                'status' => 'ACTIVE',
            ],
        );

        Wallet::query()->firstOrCreate(
            ['user_id' => $userId, 'currency' => 'VND'],
            [
                'ledger_account_id' => $account->id,
                'balance' => 0,
                'reserved_withdrawal_amount' => 0,
                'status' => 'ACTIVE',
                'version' => 1,
            ],
        );
    }

    private function load(DriverProfile $profile): DriverProfile
    {
        return $profile->load([
            'user',
            'documents.vehicle',
            'vehicles.vehicleType',
            'vehicles.documents.vehicle',
            'capabilities.vehicleType',
            'lastLocation',
        ]);
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(DriverProfile $profile): array
    {
        return [
            'review_status' => $profile->review_status->value,
            'availability_status' => $profile->availability_status->value,
            'review_reason_code' => $profile->review_reason_code,
        ];
    }

    /** @param array<string, mixed> $before */
    private function audit(
        DriverProfile $profile,
        User $admin,
        string $action,
        array $before,
        ?string $reasonCode = null,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_role' => RoleKey::Admin->value,
            'action' => $action,
            'subject_type' => DriverProfile::class,
            'subject_id' => $profile->id,
            'before' => $before,
            'after' => $this->auditSnapshot($profile),
            'reason_code' => $reasonCode,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function throwInvalidState(): never
    {
        throw ValidationException::withMessages([
            'application' => ['Không thể xét duyệt hồ sơ tài xế ở trạng thái hiện tại.'],
        ]);
    }
}
