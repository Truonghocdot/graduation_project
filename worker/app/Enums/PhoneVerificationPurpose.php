<?php

namespace App\Enums;

enum PhoneVerificationPurpose: string
{
    case Register = 'REGISTER';
    case ResetPassword = 'RESET_PASSWORD';
    case ChangePhone = 'CHANGE_PHONE';
}
