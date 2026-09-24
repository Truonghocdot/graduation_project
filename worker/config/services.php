<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'goong' => [
        'base_url' => env('GOONG_BASE_URL', 'https://rsapi.goong.io'),
        'api_key' => env('GOONG_API_KEY'),
        'map_key' => env('GOONG_MAP_KEY'),
        'connect_timeout_seconds' => (int) env('GOONG_CONNECT_TIMEOUT_SECONDS', 3),
        'timeout_seconds' => (int) env('GOONG_TIMEOUT_SECONDS', 8),
        'retry_times' => (int) env('GOONG_RETRY_TIMES', 2),
        'retry_delay_milliseconds' => (int) env('GOONG_RETRY_DELAY_MILLISECONDS', 200),
        'cache_ttl_seconds' => (int) env('GOONG_ROUTE_CACHE_TTL_SECONDS', 300),
        'vehicle_mapping' => [
            'MOTORBIKE' => 'bike',
            'CAR_4_SEAT' => 'car',
            'CAR_7_SEAT' => 'car',
        ],
    ],

    'sepay' => [
        'webhook_secret' => env('SEPAY_WEBHOOK_SECRET'),
    ],

];
