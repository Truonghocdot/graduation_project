<?php

return [
    'expires_seconds' => (int) env('OTP_EXPIRES_SECONDS', 300),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'reset_token_expires_seconds' => (int) env('OTP_RESET_TOKEN_EXPIRES_SECONDS', 600),
    'test_code' => env(
        'OTP_TEST_CODE',
        env('APP_ENV') === 'local' ? '123456' : null,
    ),
];
