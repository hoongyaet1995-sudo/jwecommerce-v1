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
        'client_id' => env('EASYPARCEL_OAUTH_CLIENT_ID'),
        'client_secret' => env('EASYPARCEL_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('EASYPARCEL_OAUTH_REDIRECT_URI', 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback'),
        'webhook_path_token' => env('EASYPARCEL_WEBHOOK_PATH_TOKEN'),
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
