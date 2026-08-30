<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // AI-Box — chỉ gọi từ queue worker (spec 01 §2), key đọc env, cấm commit.
    'aibox' => [
        'key' => env('AIBOX_API_KEY'),
        'base_url' => env('AIBOX_BASE_URL', 'https://api.example-aibox.test/v1'),
        'model' => env('AIBOX_MODEL', 'aibox-default'),
    ],

    // payOS — sóng 2 (BE-2 stub, PAY-01), placeholder rỗng theo spec 01 §5.
    'payos' => [
        'client_id' => env('PAYOS_CLIENT_ID'),
        'api_key' => env('PAYOS_API_KEY'),
        'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
        'webhook_secret' => env('PAYOS_WEBHOOK_SECRET'),
    ],

];
