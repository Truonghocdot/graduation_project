<?php

namespace App\Services\Auth;

use App\Contracts\Auth\PhoneOtpSender;
use App\Enums\PhoneVerificationPurpose;
use LogicException;

class DevelopmentPhoneOtpSender implements PhoneOtpSender
{
    public function send(
        string $phone,
        string $code,
        PhoneVerificationPurpose $purpose,
    ): void {
        if (! app()->environment(['local', 'testing']) || config('otp.test_code') === null) {
            throw new LogicException('Chưa cấu hình dịch vụ gửi OTP qua điện thoại cho môi trường thực tế.');
        }

        // The configured test code is known by the local client; never write it to logs.
        unset($phone, $code, $purpose);
    }
}
