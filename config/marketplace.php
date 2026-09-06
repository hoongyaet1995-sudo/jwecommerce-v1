<?php

return [
    'operations_enabled' => env('MARKETPLACE_OPERATIONS_ENABLED', false),
    'oauth_acceptance_enabled' => env('MARKETPLACE_OAUTH_ACCEPTANCE_ENABLED', false),
    'shopee' => [
        'enabled' => env('MARKETPLACE_SHOPEE_ENABLED', false),
        'environment' => env('MARKETPLACE_SHOPEE_ENVIRONMENT', 'sandbox'),
        'partner_id' => env('MARKETPLACE_SHOPEE_PARTNER_ID'),
        'partner_key' => env('MARKETPLACE_SHOPEE_PARTNER_KEY'),
        'callback_url' => env('MARKETPLACE_SHOPEE_CALLBACK_URL'),
    ],
];
