<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ModeCatalogReader
{
    public function __construct(private readonly InventoryThumbnail $thumbnails) {}

    /** @return array{items:list<array<string,mixed>>,summary:array{total:int,ready:int,blocked:int,excluded:int}} */
    public function read(): array
    {
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        $mode = $configuration?->operating_mode === 'independent_stores' ? 'independent_stores' : 'shared_pool';
        $primaryImages = DB::table('product_images')
            ->selectRaw('product_id, MIN(id) as image_id')
            ->whereNull('product_variant_id')
            ->groupBy('product_id');
        $variants = DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoinSub($primaryImages, 'primary_image', 'primary_image.product_id', '=', 'p.id')
            ->leftJoin('product_images as image', 'image.id', '=', 'primary_image.image_id')
            ->orderBy('v.id')
            ->get(['v.id', 'v.sku', 'v.title as variant_title', 'v.stock as catalog_stock_snapshot', 'v.data_classification',
                'v.image_url', 'v.option_image', 'image.path as product_image',
                'p.id as product_id', 'p.title as product_title', 'p.status as product_status']);

        $balances = $this->balances($mode)->keyBy('product_variant_id');
        $mappings = DB::table('marketplace_variant_mappings as m')
            ->join('marketplace_stores as s', 's.id', '=', 'm.store_id')
            ->leftJoin('inventory_store_accounts as a', 'a.marketplace_store_id', '=', 's.id')
            ->whereIn('m.product_variant_id', $variants->pluck('id'))
            ->get(['m.product_variant_id', 's.id as store_id', 's.platform', 's.shop_name', 's.status as connection_status',
                's.data_classification as store_classification', 'a.management_status'])
            ->groupBy('product_variant_id');

        $items = $variants->map(function (object $variant) use ($balances, $mappings): array {
            $balance = $balances->get($variant->id);
            $stores = $mappings->get($variant->id, collect());
            $classification = $this->classification($variant, $balance, $stores);

            return [
                'variant_id' => (int) $variant->id,
                'product_id' => (int) $variant->product_id,
                'sku' => $variant->sku,
                'product' => $variant->product_title,
                'variant' => $variant->variant_title,
                'thumbnail_url' => $this->thumbnails->url((int) $variant->id, $variant->image_url, $variant->option_image, $variant->product_image),
                'product_status' => $variant->product_status,
                'data_classification' => $variant->data_classification,
                'catalog_stock_snapshot' => (int) $variant->catalog_stock_snapshot,
                'classification' => $classification,
                'balance_id' => $balance?->id === null ? null : (int) $balance->id,
                'physical_stock' => $balance?->physical_stock === null ? null : (int) $balance->physical_stock,
                'reserved_stock' => $balance?->reserved_stock === null ? null : (int) $balance->reserved_stock,
                'counted' => $balance?->counted_at !== null,
                'mapped_stores' => $stores->map(fn (object $store): array => [
                    'id' => (int) $store->store_id,
                    'platform' => $store->platform,
                    'name' => $store->shop_name,
                    'connection_status' => $store->connection_status,
                    'management_status' => $store->management_status ?? 'setup_required',
                ])->values()->all(),
            ];
        })->values();

        return ['items' => $items->all(), 'summary' => [
            'total' => $items->count(),
            'ready' => $items->where('classification', 'ready_to_allocate')->count(),
            'blocked' => $items->whereIn('classification', ['awaiting_first_count', 'reservation_conflict'])->count(),
            'excluded' => $items->whereIn('classification', ['sandbox_test', 'excluded_status'])->count(),
        ]];
    }

    /** @return Collection<int,object> */
    private function balances(string $mode): Collection
    {
        return DB::table('inventory_balances as b')
            ->join('inventory_pools as pool', 'pool.id', '=', 'b.inventory_pool_id')
            ->leftJoin('inventory_store_accounts as account', 'account.marketplace_store_id', '=', 'pool.marketplace_store_id')
            ->leftJoin('marketplace_variant_mappings as mapping', function ($join): void {
                $join->on('mapping.product_variant_id', '=', 'b.product_variant_id')
                    ->on('mapping.store_id', '=', 'pool.marketplace_store_id');
            })
            ->where(function ($query) use ($mode): void {
                if ($mode === 'shared_pool') {
                    $query->where('pool.kind', 'shared');
                } else {
                    $query->where('pool.kind', 'store')
                        ->where(fn ($accounts) => $accounts->whereNull('pool.marketplace_store_id')
                            ->orWhereNotNull('mapping.id'));
                }
            })
            ->selectRaw('MIN(b.id) as id, b.product_variant_id, SUM(b.physical_stock) as physical_stock, SUM(b.reserved_stock) as reserved_stock, MIN(b.counted_at) as counted_at')
            ->groupBy('b.product_variant_id')
            ->get();
    }

    /** @param Collection<int,object> $stores */
    private function classification(object $variant, ?object $balance, Collection $stores): string
    {
        if ($variant->data_classification === 'test' || $stores->contains(fn (object $store) => $store->store_classification === 'test')) {
            return 'sandbox_test';
        }
        if (! in_array($variant->product_status, ['active'], true)) {
            return 'excluded_status';
        }
        if (! $balance || $balance->counted_at === null || $balance->physical_stock === null) {
            return 'awaiting_first_count';
        }
        if ((int) ($balance->reserved_stock ?? 0) > 0) {
            return 'reservation_conflict';
        }

        return 'ready_to_allocate';
    }
}
