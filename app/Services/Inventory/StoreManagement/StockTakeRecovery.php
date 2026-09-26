<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reviews pre-count Lazada test orders without changing their provider or stock allocations. */
final class StockTakeRecovery
{
    public function inspect(int $storeId, int $facilityId, int $variantId): array
    {
        $context = $this->context($storeId, $facilityId, $variantId);
        $orders = [];
        foreach (DB::table('marketplace_orders')->where('store_id', $storeId)
            ->whereNotIn('platform_status', ['cancelled', 'canceled', 'delivered', 'returned', 'refunded'])
            ->orderBy('id')->get() as $order) {
            try {
                $payload = json_decode(Crypt::decryptString($order->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
                foreach ($payload['order_items'] ?? [] as $line) {
                    if (! is_array($line) || (string) ($line['product_id'] ?? '') !== $context['product_id']
                        || (string) ($line['sku_id'] ?? '') !== $context['sku_id']
                        || (isset($line['warehouse_code']) && (string) $line['warehouse_code'] !== $context['provider_facility_id'])) {
                        continue;
                    }
                    $lineKey = (string) ($line['order_item_id'] ?? '');
                    $state = $lineKey === '' ? null : DB::table('marketplace_inventory_items')
                        ->where('order_id', $order->id)->where('line_key', $lineKey)->first();
                    $allocated = $lineKey !== '' && DB::table('stock_allocations')
                        ->where('owner_type', 'marketplace_order')->where('owner_id', $order->id)
                        ->where('line_key', $lineKey)->exists();
                    $orders[] = [
                        'id' => (int) $order->id, 'reference' => $order->external_order_id,
                        'status' => $order->platform_status, 'line_key' => $lineKey,
                        'historical' => $state?->status === 'skipped_historical' && ! $allocated,
                        'can_review_historical' => $lineKey !== '' && ! $allocated
                            && ($state === null || $state->warehouse_code === $context['provider_facility_id'])
                            && ($state === null || in_array($state->status, ['waiting', 'skipped_unmapped', 'skipped_historical'], true))
                            && ! DB::table('inventory_store_order_lines')->where('product_variant_id', $variantId)
                                ->where('reserved_quantity', '>', 0)->exists(),
                    ];
                }
            } catch (\Throwable) {
                $orders[] = ['id' => (int) $order->id, 'reference' => $order->external_order_id,
                    'status' => $order->platform_status, 'line_key' => '', 'historical' => false,
                    'can_review_historical' => false];
            }
        }
        $acceptance = app(LazadaTestFacilityAcceptance::class)->inspect($storeId, $variantId, $facilityId);

        return ['store_id' => $storeId, 'facility_id' => $facilityId, 'variant_id' => $variantId,
            'orders' => $orders, 'eligible' => $acceptance['eligible'], 'blockers' => $acceptance['blockers'],
            'facility' => collect($acceptance['facilities'])->firstWhere('id', $facilityId)];
    }

    public function reviewHistorical(User $actor, int $storeId, int $facilityId, int $variantId,
        array $orderIds, string $reason): array
    {
        abort_unless($actor->exists && $actor->is_admin && $actor->is_super_admin, 403);
        if ($orderIds === [] || trim($reason) === '') {
            throw ValidationException::withMessages(['orders' => 'Select reviewed historical orders and provide evidence.']);
        }

        return DB::transaction(function () use ($actor, $storeId, $facilityId, $variantId, $orderIds, $reason): array {
            DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            foreach (array_unique($orderIds) as $orderId) {
                DB::table('marketplace_orders')->where('id', $orderId)->where('store_id', $storeId)->lockForUpdate()->first();
            }
            $snapshot = $this->inspect($storeId, $facilityId, $variantId);
            $byId = collect($snapshot['orders'])->keyBy('id');
            $listing = $this->context($storeId, $facilityId, $variantId);
            foreach (array_unique($orderIds) as $orderId) {
                $order = $byId->get((int) $orderId);
                if (! $order || ! $order['can_review_historical']
                    || collect($snapshot['orders'])->where('id', (int) $orderId)->count() !== 1) {
                    throw ValidationException::withMessages(['orders' => 'An order is active, changed, unreadable, or outside this SKU and store. Resolve it in Orders first.']);
                }
                $lineKey = $order['line_key'];
                if ($order['historical']) {
                    continue;
                }
                DB::table('marketplace_inventory_items')->updateOrInsert(
                    ['order_id' => $orderId, 'line_key' => $lineKey],
                    ['external_product_id' => $listing['product_id'], 'external_variant_id' => $listing['sku_id'],
                        'warehouse_code' => $listing['provider_facility_id'], 'status' => 'skipped_historical',
                        'platform_updated_at' => (int) DB::table('marketplace_orders')->where('id', $orderId)->value('platform_updated_at'),
                        'stock_allocation_id' => null, 'created_at' => now(), 'updated_at' => now()]
                );
                DB::table('marketplace_stock_take_order_reviews')->insert([
                    'marketplace_store_id' => $storeId, 'marketplace_store_facility_id' => $facilityId,
                    'product_variant_id' => $variantId, 'order_id' => $orderId, 'line_key' => $lineKey,
                    'actor_id' => $actor->id, 'decision' => 'historical', 'reason' => trim($reason),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $this->inspect($storeId, $facilityId, $variantId);
        }, 3);
    }

    private function context(int $storeId, int $facilityId, int $variantId): array
    {
        abort_unless(LazadaTestFacilityAcceptance::appliesToStore($storeId), 404);
        $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)
            ->where('marketplace_store_id', $storeId)->where('is_active', true)->first();
        abort_unless($facility, 404);
        $listing = DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', $storeId)->where('listing.store_id', $storeId)
            ->where('mapping.product_variant_id', $variantId)
            ->first(['listing.external_product_id', 'listing.external_variant_id']);
        abort_unless($listing, 404);

        return ['product_id' => (string) $listing->external_product_id,
            'sku_id' => (string) $listing->external_variant_id,
            'provider_facility_id' => (string) $facility->provider_facility_id];
    }
}
