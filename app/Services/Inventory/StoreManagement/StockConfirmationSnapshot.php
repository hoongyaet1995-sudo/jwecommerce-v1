<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

/** One task snapshot owns its status, observation and current error. */
final class StockConfirmationSnapshot
{
    public static function current(object $task, ?object $balance = null): array
    {
        $balance ??= DB::table('inventory_balances')->find($task->inventory_balance_id);
        $store = DB::table('marketplace_stores')->find($task->marketplace_store_id);
        $listing = DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', $task->marketplace_store_id)->where('mapping.product_variant_id', $task->product_variant_id)
            ->where('listing.store_id', $task->marketplace_store_id)->first(['listing.*']);
        $exact = $listing && (string) $listing->external_product_id === (string) $task->provider_external_product_id
            && (string) $listing->external_variant_id === (string) $task->provider_external_variant_id;
        if ($task->marketplace_store_facility_id !== null) {
            $exact = $exact && DB::table('marketplace_store_facilities')
                ->where('id', $task->marketplace_store_facility_id)->where('marketplace_store_id', $task->marketplace_store_id)
                ->where('provider_facility_id', $task->provider_location_id)->where('is_active', true)->exists();
        }

        return self::task($task, $balance, $exact ? (int) $listing->id : 0,
            $store ? (string) $store->external_shop_id : '');
    }

    public static function task(?object $task, ?object $balance, int $listingId, ?string $shopId = null): array
    {
        $receipt = $task ? json_decode($task->provider_receipt ?? '{}', true) ?: [] : [];
        $current = $task && $balance && $balance->current_stock !== null
            && (int) $task->inventory_balance_id === (int) $balance->id
            && (int) $task->balance_version === (int) $balance->version
            && (int) $task->target_quantity === (int) $balance->current_stock
            && (int) $task->listing_variant_id === $listingId
            && ($shopId === null || ! isset($receipt['provider_shop_id']) || (string) $receipt['provider_shop_id'] === $shopId);
        $exact = ($receipt['provider_location_id'] ?? null) === ($task?->provider_location_id ?? null);
        $quantity = $current && $exact && is_int($receipt['observed_quantity'] ?? null) ? $receipt['observed_quantity'] : null;
        $status = ! $task ? 'Not synced' : (! $current ? 'Stock changed' : match ($task->status) {
            'confirmed' => $quantity === (int) $balance->current_stock ? 'Synced' : 'Confirmation needs review',
            'uncertain' => ($task->readback_exhausted_at ?? null) !== null ? 'Confirmation needs review' : 'Confirming stock',
            'queued', 'processing', 'facility_pending', 'waiting_source' => 'Confirming stock',
            'auth_expired' => 'Reconnect store',
            'failed', 'facility_blocked' => 'Sync failed',
            default => 'Not synced',
        });

        return [
            'task_id' => $task?->id, 'operation_id' => $task?->operation_id,
            'store_id' => $task ? (int) $task->marketplace_store_id : null,
            'variant_id' => $task ? (int) $task->product_variant_id : null,
            'facility_id' => $task?->marketplace_store_facility_id === null ? null : (int) $task->marketplace_store_facility_id,
            'provider_location_id' => $task?->provider_location_id,
            'provider_shop_id' => $receipt['provider_shop_id'] ?? $shopId,
            'balance_version' => $task ? (int) $task->balance_version : null,
            'target_quantity' => $current ? (int) $task->target_quantity : null,
            'status' => $status, 'quantity' => $quantity,
            'write_accepted' => $receipt['write_accepted'] ?? ($task?->status === 'confirmed'),
            'write_request_id' => $receipt['write_request_id'] ?? $receipt['request_id'] ?? null,
            'last_checked_at' => $receipt['readback_checked_at'] ?? $receipt['observed_at'] ?? $receipt['confirmed_at'] ?? null,
            'check_count' => (int) ($task?->readback_attempts ?? 0),
            'error' => in_array($status, ['Synced', 'Stock changed'], true) ? null : ($receipt['message'] ?? $receipt['code'] ?? null),
            'can_check' => $current && isset($receipt['provider_shop_id']) && $task->operation_id !== null && in_array($task->status, ['uncertain', 'failed'], true),
        ];
    }
}
