<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class OverviewReader
{
    /** @return array<string,mixed> */
    public function read(array $filters = []): array
    {
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        $mode = $configuration?->operating_mode === 'independent_stores' ? 'independent' : 'shared';
        $query = DB::table('inventory_balances as b')
            ->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
            ->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')
            ->join('products as product', 'product.id', '=', 'v.product_id')
            ->leftJoin('marketplace_stores as store', 'store.id', '=', 'p.marketplace_store_id')
            ->select(['b.id', 'b.product_variant_id', 'b.physical_stock', 'b.reserved_stock', 'b.safety_stock',
                'b.counted_at', 'b.version', 'p.pool_key', 'p.kind', 'p.marketplace_store_id',
                'store.platform', 'store.shop_name', 'store.status as connection_status', 'v.sku', 'v.title as variant_title',
                'product.title as product_title']);
        if (! empty($filters['platform'])) {
            $query->where('store.platform', $filters['platform']);
        }
        if (! empty($filters['sku'])) {
            $query->where('v.sku', 'like', '%'.$filters['sku'].'%');
        }
        $rows = $query->orderBy('b.id')->get()->map(function (object $row): array {
            $physical = $row->physical_stock === null ? null : (int) $row->physical_stock;
            $reserved = $row->reserved_stock === null ? null : (int) $row->reserved_stock;
            $safety = $row->safety_stock === null ? null : (int) $row->safety_stock;
            $available = $physical === null || $reserved === null || $safety === null
                ? null : max(0, $physical - $reserved - $safety);

            return [
                'balance_id' => (int) $row->id,
                'variant_id' => (int) $row->product_variant_id,
                'sku' => $row->sku,
                'product' => $row->product_title,
                'variant' => $row->variant_title,
                'account_key' => $row->kind === 'shared' ? 'shared'
                    : ($row->marketplace_store_id ? 'marketplace:'.$row->marketplace_store_id : 'website'),
                'store_id' => $row->marketplace_store_id === null ? null : (int) $row->marketplace_store_id,
                'platform' => $row->platform ?? 'website',
                'physical' => $physical,
                'reserved' => $reserved,
                'available' => $available,
                'counted' => $row->counted_at !== null,
                'version' => (int) $row->version,
                'provider_observation' => null,
                'external_sync' => null,
            ];
        })->all();

        $known = ['physical' => 0, 'reserved' => 0, 'available' => 0];
        $unknown = 0;
        foreach ($rows as $row) {
            if (! $row['counted'] || $row['physical'] === null || $row['reserved'] === null || $row['available'] === null) {
                $unknown++;
                continue;
            }
            $known['physical'] += $row['physical'];
            $known['reserved'] += $row['reserved'];
            $known['available'] += $row['available'];
        }

        return ['mode' => $mode, 'configuration_version' => (int) ($configuration?->version ?? 0),
            'totals' => ['known' => $known, 'unknown_accounts' => $unknown],
            'stores' => $this->stores($filters), 'data' => $rows];
    }

    /** @return list<array<string,mixed>> */
    public function stores(array $filters = []): array
    {
        $query = DB::table('marketplace_stores as s')
            ->leftJoin('inventory_store_accounts as a', 'a.marketplace_store_id', '=', 's.id')
            ->whereIn('s.platform', ['shopee', 'lazada']);
        if (! empty($filters['platform'])) {
            $query->where('s.platform', $filters['platform']);
        }

        return $query->orderBy('s.id')->get(['s.id', 's.platform', 's.shop_name', 's.region', 's.status',
            'a.management_status', 'a.version as management_version'])->map(fn (object $store): array => [
                'id' => (int) $store->id,
                'platform' => $store->platform,
                'name' => $store->shop_name,
                'region' => $store->region,
                'connection_status' => $store->status,
                'management_status' => $store->management_status ?? 'not_managed',
                'management_version' => (int) ($store->management_version ?? 0),
            ])->all();
    }

    /** @return array<string,mixed> */
    public function history(array $filters = []): array
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = DB::table('inventory_store_operations as o')->join('users as u', 'u.id', '=', 'o.actor_id');
        if (! empty($filters['kind'])) {
            $query->where('o.kind', $filters['kind']);
        }
        $total = (clone $query)->count();
        $data = $query->orderByDesc('o.created_at')->orderByDesc('o.id')
            ->forPage($page, $perPage)->get(['o.id', 'o.review_id', 'o.kind', 'o.ledger_status', 'o.receipt',
                'o.created_at', 'u.name as actor_name'])->map(fn (object $row): array => [
                    'id' => $row->id, 'review_id' => $row->review_id, 'kind' => $row->kind,
                    'ledger_status' => $row->ledger_status, 'actor' => $row->actor_name,
                    'created_at' => $row->created_at,
                    'result' => $this->sanitize(json_decode($row->receipt, true, 512, JSON_THROW_ON_ERROR)),
                ])->all();

        return ['data' => $data, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage))]];
    }

    /** @return array<string,mixed> */
    public function operation(string $id): array
    {
        $operation = DB::table('inventory_store_operations')->where('id', $id)->first();
        abort_unless($operation, 404);
        $receipt = $this->sanitize(json_decode($operation->receipt, true, 512, JSON_THROW_ON_ERROR));
        $stages = DB::table('inventory_store_sync_tasks')->where('operation_id', $id)->orderBy('created_at')->get()
            ->map(fn (object $stage): array => ['id' => $stage->id, 'store_id' => $stage->marketplace_store_id,
                'variant_id' => (int) $stage->product_variant_id, 'stage' => $stage->stage, 'status' => $stage->status,
                'target_quantity' => $stage->target_quantity, 'attempts' => (int) $stage->attempts,
                'provider_receipt' => $stage->provider_receipt ? $this->sanitize(json_decode($stage->provider_receipt, true, 512, JSON_THROW_ON_ERROR)) : null]);

        return [...$receipt, 'external_stages' => $stages->all()];
    }

    private function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $blocked = ['access_token', 'refresh_token', 'secret', 'authorization', 'raw_payload'];
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                unset($value[$key]);
            } else {
                $value[$key] = $this->sanitize($item);
            }
        }

        return $value;
    }
}
