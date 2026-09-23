<?php

return [
    'topup_ttl_minutes' => (int) env('TOPUP_TTL_MINUTES', 30),
    'withdrawal_min_amount' => (float) env('WITHDRAWAL_MIN_AMOUNT', 50_000),
    'withdrawal_max_amount' => (float) env('WITHDRAWAL_MAX_AMOUNT', 50_000_000),
    'driver_daily_cod_limit' => (float) env('DRIVER_DAILY_COD_LIMIT', 8_000_000),
    'cod_business_timezone' => env('COD_BUSINESS_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'vietqr' => [
        'bank_code' => env('VIETQR_BANK_CODE', 'MB'),
        'account_number' => env('VIETQR_ACCOUNT_NUMBER', ''),
        'account_name' => env('VIETQR_ACCOUNT_NAME', ''),
    ],
];
