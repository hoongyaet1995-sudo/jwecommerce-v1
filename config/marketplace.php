<?php

return [
    'basic_wms_ui_enabled' => env('MARKETPLACE_BASIC_WMS_UI_ENABLED', false),
    'basic_wms_enabled' => env('MARKETPLACE_BASIC_WMS_ENABLED', false),
    'advanced_wms_enabled' => env('MARKETPLACE_ADVANCED_WMS_ENABLED', false),
    'sandbox_stock_writes_enabled' => env('MARKETPLACE_SANDBOX_STOCK_WRITES_ENABLED', false),
    'warehouse_transfers_enabled' => env('MARKETPLACE_WAREHOUSE_TRANSFERS_ENABLED', false),
    'lazada' => [
        'online_test_access_enabled' => env('MARKETPLACE_LAZADA_ONLINE_TEST_ACCESS_ENABLED', false),
        'test_store_id' => env('MARKETPLACE_LAZADA_TEST_STORE_ID'),
        'enabled' => env('MARKETPLACE_LAZADA_ENABLED', false),
        'app_key' => env('MARKETPLACE_LAZADA_APP_KEY'),
        'app_secret' => env('MARKETPLACE_LAZADA_APP_SECRET'),
        'callback_url' => env('MARKETPLACE_LAZADA_CALLBACK_URL'),
    ],
    'automatic_orders_enabled' => env('MARKETPLACE_AUTOMATIC_ORDERS_ENABLED', false),
    'inventory_accounting_enabled' => env('MARKETPLACE_INVENTORY_ACCOUNTING_ENABLED', false),
    'publish_operations_enabled' => env('MARKETPLACE_PUBLISH_OPERATIONS_ENABLED', false),
    'fulfillment_operations_enabled' => env('MARKETPLACE_FULFILLMENT_OPERATIONS_ENABLED', false),
    'operations_enabled' => env('MARKETPLACE_OPERATIONS_ENABLED', false),
    'read_operations_enabled' => env('MARKETPLACE_READ_OPERATIONS_ENABLED', false),
    'stock_sync_operations_enabled' => env('MARKETPLACE_STOCK_SYNC_OPERATIONS_ENABLED', false),
    'import_operations_enabled' => env('MARKETPLACE_IMPORT_OPERATIONS_ENABLED', false),
    'oauth_acceptance_enabled' => env('MARKETPLACE_OAUTH_ACCEPTANCE_ENABLED', false),
    'shopee' => [
        'test_store_id' => env('MARKETPLACE_SHOPEE_TEST_STORE_ID'),
        'enabled' => env('MARKETPLACE_SHOPEE_ENABLED', false),
        'environment' => env('MARKETPLACE_SHOPEE_ENVIRONMENT', 'sandbox'),
        'partner_id' => env('MARKETPLACE_SHOPEE_ENVIRONMENT', 'sandbox')==='sandbox'
            ? (env('MARKETPLACE_SHOPEE_SANDBOX_PARTNER_ID') ?: env('MARKETPLACE_SHOPEE_PARTNER_ID')) : env('MARKETPLACE_SHOPEE_PARTNER_ID'),
        'partner_key' => env('MARKETPLACE_SHOPEE_ENVIRONMENT', 'sandbox')==='sandbox'
            ? (env('MARKETPLACE_SHOPEE_SANDBOX_PARTNER_KEY') ?: env('MARKETPLACE_SHOPEE_PARTNER_KEY')) : env('MARKETPLACE_SHOPEE_PARTNER_KEY'),
        'callback_url' => env('MARKETPLACE_SHOPEE_CALLBACK_URL'),
    ],
];
