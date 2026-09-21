<?php

namespace App\Services\Auth;

use App\Enums\AppType;
use App\Enums\DriverReviewStatus;
use App\Enums\PhoneVerificationPurpose;
use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\PhonePasswordResetToken;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class PhoneAuthService
{
    public function __construct(private PhoneVerificationService $verificationService) {}

    /**
     * @param  array{name: string, phone: string, email?: string|null, password: string}  $attributes
     * @return array{user: User, expires_at: string}
     */
    public function register(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $user = User::query()->create([
                'name' => $attributes['name'],
                'phone' => $attributes['phone'],
                'email' => $attributes['email'] ?? null,
                'password' => $attributes['password'],
                'status' => UserStatus::PendingVerification,
            ]);

            $verification = $this->verificationService->issue(
                $user,
                PhoneVerificationPurpose::Register,
            );

            return [
                'user' => $user,
                'expires_at' => $verification->expires_at->toISOString(),
            ];
        });
    }

    public function resendRegistrationCode(string $phone): void
    {
        $user = User::query()
            ->where('phone', $phone)
            ->where('status', UserStatus::PendingVerification->value)
            ->first();

        if ($user !== null) {
            $this->verificationService->issue($user, PhoneVerificationPurpose::Register);
        }
    }

    /**
     * @param  array{device_id: string, app_type: string, platform: string, push_token?: string|null}  $device
     * @return array{user: User, token: string}
     */
    public function verifyRegistration(
        string $phone,
        string $code,
        array $device,
    ): array {
        $verification = $this->verificationService->verify(
            $phone,
            PhoneVerificationPurpose::Register,
            $code,
        );

        return DB::transaction(function () use ($verification, $device): array {
            $user = User::query()->lockForUpdate()->findOrFail($verification->user_id);

            if ($user->status !== UserStatus::PendingVerification) {
                throw ValidationException::withMessages([
                    'phone' => ['The phone number cannot be verified in its current state.'],
                ]);
            }

            $user->forceFill([
                'phone_verified_at' => now(),
                'status' => UserStatus::Active,
            ])->save();

            $customerRoleId = Role::query()
                ->where('key', RoleKey::Customer->value)
                ->value('id');

            if ($customerRoleId === null) {
                throw new LogicException('The CUSTOMER role has not been seeded.');
            }

            $user->roles()->syncWithoutDetaching([
                $customerRoleId => ['granted_at' => now()],
            ]);

            return $this->issueSession($user, $device);
        });
    }

    /**
     * @param  array{device_id: string, app_type: string, platform: string, push_token?: string|null}  $device
     * @return array{user: User, token: string}
     */
    public function login(
        string $phone,
        string $password,
        array $device,
    ): array {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are invalid.'],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'phone' => ['The account is not active.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->issueSession($user, $device);
    }

    public function startPasswordReset(string $phone): void
    {
        $user = User::query()
            ->where('phone', $phone)
            ->where('status', UserStatus::Active->value)
            ->first();

        if ($user !== null) {
            $this->verificationService->issue($user, PhoneVerificationPurpose::ResetPassword);
        }
    }

    public function issuePasswordResetToken(string $phone, string $code): string
    {
        $verification = $this->verificationService->verify(
            $phone,
            PhoneVerificationPurpose::ResetPassword,
            $code,
        );

        return DB::transaction(function () use ($verification): string {
            PhonePasswordResetToken::query()
                ->where('user_id', $verification->user_id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $plainToken = Str::random(64);

            PhonePasswordResetToken::query()->create([
                'user_id' => $verification->user_id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addSeconds(config('otp.reset_token_expires_seconds')),
            ]);

            return $plainToken;
        });
    }

    public function resetPassword(string $phone, string $token, string $password): void
    {
        DB::transaction(function () use ($phone, $token, $password): void {
            $user = User::query()->where('phone', $phone)->lockForUpdate()->first();

            $resetToken = $user === null
                ? null
                : PhonePasswordResetToken::query()
                    ->where('user_id', $user->id)
                    ->where('token_hash', hash('sha256', $token))
                    ->whereNull('used_at')
                    ->lockForUpdate()
                    ->first();

            if ($resetToken === null || $resetToken->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => ['The password reset token is invalid or expired.'],
                ]);
            }

            $user->forceFill(['password' => $password])->save();
            $resetToken->forceFill(['used_at' => now()])->save();
            $user->tokens()->delete();
            $user->devices()->update(['revoked_at' => now()]);
        });
    }

    /**
     * @param  array{device_id: string, app_type: string, platform: string, push_token?: string|null}  $device
     * @return array{user: User, token: string}
     */
    private function issueSession(User $user, array $device): array
    {
        $appType = AppType::from($device['app_type']);

        if (
            $appType === AppType::Driver
            && (
                ! $user->hasRole(RoleKey::Driver)
                || $user->driverProfile?->review_status !== DriverReviewStatus::Approved
            )
        ) {
            throw ValidationException::withMessages([
                'app_type' => ['This account is not approved for the driver application.'],
            ]);
        }

        UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $device['device_id'],
                'app_type' => $appType->value,
            ],
            [
                'platform' => $device['platform'],
                'push_token' => $device['push_token'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        $token = $user->createToken(
            $appType->value.':'.$device['device_id'],
            $appType->abilities(),
        );

        return [
            'user' => $user->load('roles'),
            'token' => $token->plainTextToken,
        ];
    }
}
