<?php

namespace App\Services\Inventory\StoreManagement;

final class StockSyncPolicy
{
    /**
     * @return array{allowed: bool, code: string, next_action: string}
     */
    public function review(object $store, object $listing): array
    {
        $platform = strtolower(trim((string) ($store->platform ?? '')));
        $storeId = filter_var($store->id ?? null, FILTER_VALIDATE_INT);
        $listingId = filter_var($listing->id ?? null, FILTER_VALIDATE_INT);

        if (! in_array($platform, ['shopee', 'lazada'], true)) {
            return $this->deny('platform_not_supported', 'Use a supported marketplace provider.');
        }

        if ($storeId === false || $listingId === false || (int) ($listing->store_id ?? 0) !== $storeId) {
            return $this->deny('listing_identity_invalid', 'Refresh the persisted SKU mapping.');
        }

        if (($store->status ?? null) !== 'connected') {
            return $this->deny('store_not_connected', 'Reconnect the store account.');
        }

        if (config('marketplace.stock_sync_operations_enabled') !== true) {
            return $this->deny('stock_sync_disabled', 'Enable the master stock sync switch.');
        }

        if (config("marketplace.stock_sync_platforms.{$platform}") !== true) {
            return $this->deny('platform_stock_sync_disabled', "Enable {$platform} stock sync.");
        }

        if (config('marketplace.stock_sync_canary_enforced', true) !== true) {
            return $this->allow();
        }

        $identities = config('marketplace.stock_sync_canary_identities', []);
        $parsed = $this->parseCanaryIdentities(is_array($identities) ? $identities : explode(',', (string) $identities));
        if ($parsed === null) {
            return $this->deny('canary_configuration_invalid', 'Correct the server-side canary allowlist.');
        }

        $identity = "{$platform}:{$storeId}:{$listingId}";
        $suffix = ":{$storeId}:{$listingId}";
        foreach ($parsed as $candidate) {
            if (str_ends_with($candidate, $suffix) && $candidate !== $identity) {
                return $this->deny('canary_configuration_invalid', 'Correct the server-side canary allowlist.');
            }
        }

        if (! in_array($identity, $parsed, true)) {
            return $this->deny('canary_not_allowlisted', 'Add this persisted listing identity to the server allowlist.');
        }

        return $this->allow();
    }

    /** @param array<int, mixed> $identities */
    private function parseCanaryIdentities(array $identities): ?array
    {
        $parsed = [];
        foreach ($identities as $raw) {
            $identity = trim((string) $raw);
            if (! preg_match('/^(shopee|lazada):([1-9][0-9]*):([1-9][0-9]*)$/', $identity)) {
                return null;
            }
            if (isset($parsed[$identity])) {
                return null;
            }
            $parsed[$identity] = true;
        }

        return array_keys($parsed);
    }

    private function allow(): array
    {
        return ['allowed' => true, 'code' => 'ready', 'next_action' => 'Dispatch the reviewed stock sync task.'];
    }

    private function deny(string $code, string $nextAction): array
    {
        return ['allowed' => false, 'code' => $code, 'next_action' => $nextAction];
    }
}
