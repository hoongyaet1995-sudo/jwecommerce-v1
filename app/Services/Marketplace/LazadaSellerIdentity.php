<?php

namespace App\Services\Marketplace;

use RuntimeException;

/** Confirms that a provider token still belongs to the Lazada store selected in JW. */
final class LazadaSellerIdentity
{
    public static function assertCurrent(object $store, array $identity, string $context): void
    {
        if (!is_string($store->external_shop_id ?? null) || !preg_match('/^[1-9][0-9]*$/D', $store->external_shop_id)
            || (string) ($identity['seller_id'] ?? '') !== $store->external_shop_id
            || ($identity['status'] ?? null) !== 'ACTIVE') {
            throw new RuntimeException('Lazada seller identity changed '.$context.'.');
        }
    }
}
