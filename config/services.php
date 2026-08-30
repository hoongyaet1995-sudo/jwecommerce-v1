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

    'easyparcel' => [
        'client_id' => env('EASYPARCEL_SANDBOX_OAUTH_CLIENT_ID', env('EASYPARCEL_OAUTH_CLIENT_ID')),
        'client_secret' => env('EASYPARCEL_SANDBOX_OAUTH_CLIENT_SECRET', env('EASYPARCEL_OAUTH_CLIENT_SECRET')),
        'redirect_uri' => env('EASYPARCEL_OAUTH_REDIRECT_URI', 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback'),
        'operating_environment' => env('EASYPARCEL_OPERATING_ENVIRONMENT', 'sandbox'),
        'environments' => [
            'sandbox' => [
                'authorization_enabled' => true,
                'client_id' => env('EASYPARCEL_SANDBOX_OAUTH_CLIENT_ID', env('EASYPARCEL_OAUTH_CLIENT_ID')),
                'client_secret' => env('EASYPARCEL_SANDBOX_OAUTH_CLIENT_SECRET', env('EASYPARCEL_OAUTH_CLIENT_SECRET')),
                'authorization_url' => env('EASYPARCEL_SANDBOX_OAUTH_AUTHORIZATION_URL', 'https://api.easyparcel.com/oauth/login'),
                'token_url' => env('EASYPARCEL_SANDBOX_OAUTH_TOKEN_URL', 'https://api.easyparcel.com/oauth/token'),
                'wallet_url' => env('EASYPARCEL_SANDBOX_OAUTH_WALLET_URL', 'https://api.easyparcel.com/open_api/2026-06/wallet'),
            ],
            'production' => [
                'authorization_enabled' => env('EASYPARCEL_PRODUCTION_AUTHORIZATION_ENABLED', false),
                'client_id' => env('EASYPARCEL_PRODUCTION_OAUTH_CLIENT_ID'),
                'client_secret' => env('EASYPARCEL_PRODUCTION_OAUTH_CLIENT_SECRET'),
                'authorization_url' => env('EASYPARCEL_PRODUCTION_OAUTH_AUTHORIZATION_URL'),
                'token_url' => env('EASYPARCEL_PRODUCTION_OAUTH_TOKEN_URL'),
                'wallet_url' => env('EASYPARCEL_PRODUCTION_OAUTH_WALLET_URL'),
            ],
        ],
        'webhook_path_token' => env('EASYPARCEL_WEBHOOK_PATH_TOKEN'),
        'reconciliation_stale_queue_seconds' => env('EASYPARCEL_RECONCILIATION_STALE_QUEUE_SECONDS', 120),
        'awb_alert_after_minutes' => env('EASYPARCEL_AWB_ALERT_AFTER_MINUTES', 60),
        'awb_review_after_minutes' => env('EASYPARCEL_AWB_REVIEW_AFTER_MINUTES', 10),
        'cron_lock_seconds' => env('EASYPARCEL_CRON_LOCK_SECONDS', 180),
        'sender' => [
            'name' => env('EASYPARCEL_SENDER_NAME'),
            'company' => env('EASYPARCEL_SENDER_COMPANY'),
            'phone_number_country_code' => env('EASYPARCEL_SENDER_PHONE_COUNTRY_CODE', 'MY'),
            'phone_number' => env('EASYPARCEL_SENDER_PHONE'),
            'email' => env('EASYPARCEL_SENDER_EMAIL'),
            'address_1' => env('EASYPARCEL_SENDER_ADDRESS_1'),
            'address_2' => env('EASYPARCEL_SENDER_ADDRESS_2'),
            'postcode' => env('EASYPARCEL_SENDER_POSTCODE'),
            'city' => env('EASYPARCEL_SENDER_CITY'),
            'subdivision_code' => env('EASYPARCEL_SENDER_SUBDIVISION_CODE'),
            'country_code' => env('EASYPARCEL_SENDER_COUNTRY_CODE', 'MY'),
        ],
    ],

];
