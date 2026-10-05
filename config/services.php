<?php

return [
    'instagram'=>[
      'inbound_routing_ready'=>env('INSTAGRAM_INBOUND_ROUTING_READY',false),
      'webhook_verify_token'=>env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN',env('FACEBOOK_WEBHOOK_VERIFY_TOKEN')),
    ],

    'facebook' => [
        'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN'),
        'inbound_routing_ready' => env('FACEBOOK_INBOUND_ROUTING_READY', false),
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', rtrim(env('APP_URL', ''), '/').'/admin/social/facebook/callback'),
        'merchant_key' => env('SOCIAL_MERCHANT_KEY', 'jw-ecommerce'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v25.0'),
    ],

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
        'client_id' => env('EASYPARCEL_OAUTH_CLIENT_ID'),
        'client_secret' => env('EASYPARCEL_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('EASYPARCEL_OAUTH_REDIRECT_URI', 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback'),
        'operating_environment' => env('EASYPARCEL_OPERATING_ENVIRONMENT', 'sandbox'),
        'environments' => [
            'sandbox' => [
                'authorization_enabled' => true,
                'client_id' => env('EASYPARCEL_OAUTH_CLIENT_ID'),
                'client_secret' => env('EASYPARCEL_OAUTH_CLIENT_SECRET'),
                'authorization_url' => 'https://api.easyparcel.com/oauth/login',
                'token_url' => 'https://api.easyparcel.com/oauth/token',
                'wallet_url' => 'https://api.easyparcel.com/open_api/2026-06/wallet',
            ],
            'production' => [
                'authorization_enabled' => env('EASYPARCEL_PRODUCTION_AUTHORIZATION_ENABLED', false),
                'client_id' => env('EASYPARCEL_OAUTH_CLIENT_ID'),
                'client_secret' => env('EASYPARCEL_OAUTH_CLIENT_SECRET'),
                'authorization_url' => 'https://api.easyparcel.com/oauth/login',
                'token_url' => 'https://api.easyparcel.com/oauth/token',
                'wallet_url' => 'https://api.easyparcel.com/open_api/2026-06/wallet',
            ],
        ],
        'webhook_path_token' => env('EASYPARCEL_WEBHOOK_PATH_TOKEN'),
        'reconciliation_stale_queue_seconds' => env('EASYPARCEL_RECONCILIATION_STALE_QUEUE_SECONDS', 120),
        'awb_alert_after_minutes' => env('EASYPARCEL_AWB_ALERT_AFTER_MINUTES', 60),
        'awb_review_after_minutes' => env('EASYPARCEL_AWB_REVIEW_AFTER_MINUTES', 10),
        'cron_lock_seconds' => env('EASYPARCEL_CRON_LOCK_SECONDS', 180),
    ],

];
