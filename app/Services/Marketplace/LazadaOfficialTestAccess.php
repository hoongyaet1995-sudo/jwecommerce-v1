<?php

namespace App\Services\Marketplace;

final class LazadaOfficialTestAccess
{
    public static function environmentAllows(object $store, ?string $applicationId): bool
    {
        return app()->environment('local', 'testing')
            || (config('marketplace.lazada.online_test_access_enabled') === true
                && (string) config('marketplace.lazada.test_store_id') === (string) $store->id
                && $store->data_classification === 'test'
                && $store->platform === 'lazada'
                && $store->external_shop_id === '300163725140'
                && $applicationId === (string)config('marketplace.lazada.test_app_key'));
    }
}
