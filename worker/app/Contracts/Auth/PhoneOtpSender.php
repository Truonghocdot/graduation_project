<?php

namespace App\Contracts\Auth;

use App\Enums\PhoneVerificationPurpose;

interface PhoneOtpSender
{
    public function send(
        string $phone,
        string $code,
        PhoneVerificationPurpose $purpose,
    ): void;
}
