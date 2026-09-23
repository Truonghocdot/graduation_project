<?php

namespace App\Services\Auth;

use App\Contracts\Auth\PhoneOtpSender;
use App\Enums\PhoneVerificationPurpose;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(private PhoneOtpSender $sender) {}

    public function issue(User $user, PhoneVerificationPurpose $purpose): PhoneVerification
    {
        return DB::transaction(function () use ($user, $purpose): PhoneVerification {
            PhoneVerification::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose->value)
                ->whereNull('verified_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $code = $this->generateCode();

            $verification = PhoneVerification::query()->create([
                'user_id' => $user->id,
                'phone' => $user->phone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'attempt_count' => 0,
                'max_attempts' => config('otp.max_attempts'),
                'expires_at' => now()->addSeconds(config('otp.expires_seconds')),
            ]);

            $this->sender->send($user->phone, $code, $purpose);

            return $verification;
        });
    }

    public function verify(
        string $phone,
        PhoneVerificationPurpose $purpose,
        string $code,
    ): PhoneVerification {
        /** @var array{verification: PhoneVerification|null, error: string|null} $result */
        $result = DB::transaction(function () use ($phone, $purpose, $code): array {
            $verification = PhoneVerification::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose->value)
                ->whereNull('verified_at')
                ->whereNull('invalidated_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($verification === null || $verification->expires_at->isPast()) {
                return ['verification' => null, 'error' => 'Mã xác minh không hợp lệ hoặc đã hết hạn.'];
            }

            if (! Hash::check($code, $verification->code_hash)) {
                $verification->attempt_count++;

                if ($verification->attempt_count >= $verification->max_attempts) {
                    $verification->invalidated_at = now();
                }

                $verification->save();

                return ['verification' => null, 'error' => 'Mã xác minh không hợp lệ hoặc đã hết hạn.'];
            }

            $verification->verified_at = now();
            $verification->save();

            return ['verification' => $verification, 'error' => null];
        });

        if ($result['verification'] === null) {
            throw ValidationException::withMessages([
                'code' => [$result['error']],
            ]);
        }

        return $result['verification'];
    }

    private function generateCode(): string
    {
        $testCode = config('otp.test_code');

        if (
            app()->environment(['local', 'testing'])
            && is_string($testCode)
            && preg_match('/^\d{6}$/', $testCode) === 1
        ) {
            return $testCode;
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
