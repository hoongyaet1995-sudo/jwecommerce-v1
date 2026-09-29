<?php

namespace App\Services\Marketplace;

use App\Services\Inventory\CurrentStockOrderLine;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Shared stock effects for webhook, manual pull and status refresh snapshots. */
final class CurrentStockMarketplaceOrder
{
    public function __construct(
        private readonly CurrentStockOrderLine $lines,
        private readonly AccountResolver $accounts,
    ) {}

    public function apply(object $store, object $order, array $payload): string
    {
        if ($store->platform === 'lazada' && (string) ($payload['order_id'] ?? '') !== $order->external_order_id) {
            throw new MarketplaceInventoryException('invalid_order_lines');
        }
        $created = $store->platform === 'lazada'
            ? CarbonImmutable::createFromFormat('!Y-m-d H:i:s O', (string) ($payload['created_at'] ?? ''))
            : (isset($payload['create_time']) && is_int($payload['create_time'])
                ? CarbonImmutable::createFromTimestampUTC($payload['create_time']) : null);
        if (! $created) {
            throw new MarketplaceInventoryException('invalid_order_time');
        }
        $plan = $store->platform === 'lazada'
            ? LazadaInventoryPlan::build($payload, 0)
            : $this->shopeePlan($order, $payload);
        $statuses = [];
        foreach ($plan as $line) {
            $existing = DB::table('inventory_current_stock_order_lines')
                ->where('channel_key', 'marketplace:'.$store->id)
                ->where('order_reference', $order->external_order_id)
                ->where('item_reference', $line['line_key'])->first();
            $variantId = $existing?->product_variant_id ?? DB::table('marketplace_listing_variants as listing')
                ->join('marketplace_variant_mappings as mapping', 'mapping.listing_variant_id', '=', 'listing.id')
                ->where('listing.store_id', $store->id)->where('mapping.store_id', $store->id)
                ->where('listing.external_product_id', $line['external_product_id'])
                ->where('listing.external_variant_id', $line['external_variant_id'])
                ->value('mapping.product_variant_id');
            if (! $variantId) {
                throw new MarketplaceInventoryException('sku_unmapped');
            }
            $balanceId = $existing?->inventory_balance_id ?? ($store->platform === 'lazada'
                ? $this->facilityBalance((int) $store->id, (int) $variantId, $line['warehouse_code'])
                : $this->accounts->balance((int) $variantId, 'marketplace:'.$store->id)['id']);
            $terminal = ($line['target'] ?? null) === 'released' || ($line['terminal'] ?? false);
            $quantity = (int) $line['quantity'];
            $debit = $terminal && ! $existing ? 0 : $quantity;
            $restore = $terminal && $existing ? $quantity : 0;
            $key = 'marketplace:'.$order->id.':'.$line['line_key'].':'.$order->platform_updated_at;
            try {
                $this->lines->apply('marketplace:'.$store->id, $order->external_order_id,
                    $line['line_key'], (int) $variantId, (int) $balanceId, $quantity,
                    $debit, $restore, $key, $terminal, $created->toIso8601String());
            } catch (RuntimeException $error) {
                throw new MarketplaceInventoryException('current_stock_order_conflict', previous: $error);
            }
            $statuses[] = $terminal ? 'released' : ($line['target'] ?? 'reserved');
        }

        return count(array_unique($statuses)) === 1 ? $statuses[0] : 'mixed';
    }

    private function facilityBalance(int $storeId, int $variantId, string $facilityCode): int
    {
        $balanceId = DB::table('marketplace_store_facilities as facility')
            ->join('inventory_pools as pool', 'pool.warehouse_id', '=', 'facility.inventory_warehouse_id')
            ->join('inventory_balances as balance', 'balance.inventory_pool_id', '=', 'pool.id')
            ->where('facility.marketplace_store_id', $storeId)
            ->where('facility.provider_facility_id', $facilityCode)
            ->where('facility.is_active', true)->where('pool.kind', 'facility')
            ->where('balance.product_variant_id', $variantId)->value('balance.id');
        if (! $balanceId) {
            throw new MarketplaceInventoryException('warehouse_route_mismatch');
        }

        return (int) $balanceId;
    }

    private function shopeePlan(object $order, array $payload): array
    {
        if (! is_int($payload['create_time'] ?? null)
            || ($payload['fulfillment_flag'] ?? null) !== 'fulfilled_by_local_seller'
            || ! empty($payload['advance_package'])) {
            throw new MarketplaceInventoryException('unsupported_fulfillment');
        }
        $terminal = $order->platform_status === 'CANCELLED';
        if (! $terminal && ! in_array($order->platform_status,
            ['UNPAID', 'IN_CANCEL', 'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'COMPLETED'], true)) {
            throw new MarketplaceInventoryException('unsupported_order_status');
        }
        $items = $payload['item_list'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || ! $items) {
            throw new MarketplaceInventoryException('invalid_order_lines');
        }
        $plan = [];
        $seen = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_int($item['item_id'] ?? null)
                || ! is_int($item['model_id'] ?? null)
                || ! is_int($item['model_quantity_purchased'] ?? null)
                || $item['model_quantity_purchased'] < 1 || ! empty($item['components'])) {
                throw new MarketplaceInventoryException('invalid_order_lines');
            }
            $lineKey = $item['item_id'].':'.$item['model_id'];
            if (isset($seen[$lineKey])) {
                throw new MarketplaceInventoryException('duplicate_order_line');
            }
            $seen[$lineKey] = true;
            $target = match ($order->platform_status) {
                'UNPAID', 'IN_CANCEL' => 'waiting',
                'READY_TO_SHIP', 'PROCESSED' => 'reserved',
                'SHIPPED', 'COMPLETED' => 'consumed',
                'CANCELLED' => 'released',
            };
            $plan[] = ['line_key' => $lineKey,
                'external_product_id' => (string) $item['item_id'],
                'external_variant_id' => (string) $item['model_id'],
                'warehouse_code' => 'SHOPEE', 'quantity' => $item['model_quantity_purchased'],
                'target' => $target, 'terminal' => $terminal];
        }

        return $plan;
    }
}
