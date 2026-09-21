<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class OverviewReader
{
    public function __construct(
        private readonly ModeCatalogReader $modeCatalog,
        private readonly StoreEnrollmentService $enrollment,
        private readonly StockSyncPolicy $stockSyncPolicy,
    ) {}

    /** @return array<string,mixed> */
    public function read(array $filters = []): array
    {
        $this->enrollment->ensureWebsite();
        $this->enrollment->enrollConnectedStores();
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        $mode = $configuration?->operating_mode === 'independent_stores' ? 'independent' : 'shared';
        $query = DB::table('inventory_balances as b')
            ->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
            ->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')
            ->join('products as product', 'product.id', '=', 'v.product_id')
            ->leftJoin('marketplace_stores as store', 'store.id', '=', 'p.marketplace_store_id')
            ->leftJoin('marketplace_variant_mappings as mapping', function ($join): void {
                $join->on('mapping.product_variant_id', '=', 'b.product_variant_id')
                    ->on('mapping.store_id', '=', 'p.marketplace_store_id');
            })
            ->select(['b.id', 'b.product_variant_id', 'b.physical_stock', 'b.reserved_stock', 'b.safety_stock',
                'b.counted_at', 'b.version', 'p.pool_key', 'p.kind', 'p.marketplace_store_id',
                'mapping.id as mapping_id', 'mapping.listing_variant_id',
                'store.platform', 'store.shop_name', 'store.status as connection_status', 'v.sku', 'v.title as variant_title',
                'product.title as product_title']);
        if (! empty($filters['platform'])) {
            $query->where('store.platform', $filters['platform']);
        }
        if (! empty($filters['sku'])) {
            $query->where('v.sku', 'like', '%'.$filters['sku'].'%');
        }
        $balanceRows = $query->orderBy('b.id')->get();
        $syncTasks = DB::table('inventory_store_sync_tasks')
            ->whereIn('product_variant_id', $balanceRows->pluck('product_variant_id')->unique()->values())
            ->whereNotNull('marketplace_store_id')
            ->orderByDesc('created_at')
            ->get(['id', 'product_variant_id', 'marketplace_store_id', 'status', 'target_quantity', 'attempts'])
            ->groupBy('product_variant_id');
        $tokens = DB::table('marketplace_store_tokens')->whereIn('store_id', $balanceRows->pluck('marketplace_store_id')->filter()->unique())
            ->get(['store_id', 'access_expires_at'])->keyBy('store_id');

        $rows = $balanceRows->map(function (object $row) use ($syncTasks, $tokens, $mode): array {
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
                'store_name' => $row->shop_name,
                'platform' => $row->platform ?? 'website',
                'physical' => $physical,
                'reserved' => $reserved,
                'available' => $available,
                'counted' => $row->counted_at !== null,
                'allocation_eligible' => $mode === 'shared'
                    ? $row->kind === 'shared'
                    : $row->kind === 'store' && ($row->marketplace_store_id === null || $row->mapping_id !== null),
                'version' => (int) $row->version,
                'provider_observation' => null,
                'sync_readiness' => $this->rowSyncReadiness($row, $tokens->get($row->marketplace_store_id)),
                'external_sync' => $this->syncState(
                    $syncTasks->get((int) $row->product_variant_id, collect()),
                    $row->marketplace_store_id === null ? null : (int) $row->marketplace_store_id,
                    $row->kind === 'shared'
                ),
            ];
        })->all();

        $known = ['physical' => 0, 'reserved' => 0, 'available' => 0];
        $unknown = 0;
        foreach ($rows as $row) {
            if (! $row['allocation_eligible']) {
                continue;
            }
            if (! $row['counted'] || $row['physical'] === null || $row['reserved'] === null || $row['available'] === null) {
                $unknown++;

                continue;
            }
            $known['physical'] += $row['physical'];
            $known['reserved'] += $row['reserved'];
            $known['available'] += $row['available'];
        }

        $website = DB::table('inventory_store_accounts')->where('account_key', 'website')->first();

        return ['mode' => $mode, 'configuration_version' => (int) ($configuration?->version ?? 0),
            'website_account' => ['management_status' => 'managed', 'inventory_membership' => 'enrolled', 'sync_state' => 'active',
                'management_version' => (int) ($website?->version ?? 0)],
            'totals' => ['known' => $known, 'unknown_accounts' => $unknown], 'mode_catalog' => $this->modeCatalog->read(),
            'stores' => $this->stores($filters), 'data' => $rows];
    }

    /** @return array{ready:bool,effective_state:string,blockers:list<array{code:string,next_action:string}>}|null */
    private function rowSyncReadiness(object $row, ?object $token): ?array
    {
        if ($row->marketplace_store_id === null) {
            return null;
        }

        $blockers = [];
        if ($row->mapping_id === null || $row->listing_variant_id === null) {
            $blockers[] = ['code' => 'sku_not_mapped', 'next_action' => 'Map this store listing to the JW SKU.'];
        }
        if ($row->connection_status !== 'connected') {
            $blockers[] = ['code' => 'store_not_connected', 'next_action' => 'Reconnect the store account.'];
        }
        if ($row->counted_at === null || $row->physical_stock === null) {
            $blockers[] = ['code' => 'initial_count_required', 'next_action' => 'Complete the first count for this store SKU.'];
        } elseif ((int) ($row->reserved_stock ?? 0) > (int) $row->physical_stock) {
            $blockers[] = ['code' => 'reservation_conflict', 'next_action' => 'Reconcile reserved units before stock sync.'];
        }
        if (! $token) {
            $blockers[] = ['code' => 'authorization_required', 'next_action' => 'Connect the store account.'];
        } elseif ($token->access_expires_at !== null && now()->gte($token->access_expires_at)) {
            $blockers[] = ['code' => 'authorization_expired', 'next_action' => 'Reconnect the store account.'];
        }
        if ($row->mapping_id !== null && $row->listing_variant_id !== null) {
            $policy = $this->stockSyncPolicy->review(
                (object) ['id' => $row->marketplace_store_id, 'platform' => $row->platform, 'status' => $row->connection_status],
                (object) ['id' => $row->listing_variant_id, 'store_id' => $row->marketplace_store_id],
            );
            if (! $policy['allowed']) {
                $blockers[] = ['code' => $policy['code'], 'next_action' => $policy['next_action']];
            }
        }
        $blockers = collect($blockers)->unique('code')->values()->all();

        return ['ready' => $blockers === [], 'effective_state' => $blockers === [] ? 'ready' : 'blocked', 'blockers' => $blockers];
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
                'management_status' => $store->management_status ?? 'setup_required',
                'inventory_membership' => 'enrolled',
                'sync_state' => match ($store->management_status ?? 'setup_required') {
                    'managed' => 'active',
                    'paused' => 'paused',
                    default => 'setup_required',
                },
                'management_version' => (int) ($store->management_version ?? 0),
            ])->all();
    }

    /** @return array<string,mixed> */
    public function history(array $filters = []): array
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = DB::table('inventory_store_operations as o')->join('users as u', 'u.id', '=', 'o.actor_id')->leftJoin('inventory_store_reviews as r', 'r.id', '=', 'o.review_id');
        if (! empty($filters['kind'])) {
            $query->where('o.kind', $filters['kind']);
        }
        $total = (clone $query)->count();
        $data = $query->orderByDesc('o.created_at')->orderByDesc('o.id')
            ->forPage($page, $perPage)->get(['o.id', 'o.review_id', 'o.kind', 'o.ledger_status', 'o.receipt', 'r.payload',
                'o.created_at', 'u.name as actor_name'])->map(fn (object $row): array => [
                    'id' => $row->id, 'review_id' => $row->review_id, 'kind' => $row->kind,
                    'ledger_status' => $row->ledger_status, 'actor' => $row->actor_name,
                    'created_at' => $row->created_at,
                    'result' => [...$this->sanitize(json_decode($row->receipt, true, 512, JSON_THROW_ON_ERROR)),
                        'payload' => $row->payload ? json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR) : null,
                        'external_stages' => DB::table('inventory_store_sync_tasks')->where('operation_id', $row->id)->orderBy('created_at')->get()->map(fn ($task) => (array) $task)->all()],
                ])->all();

        return ['data' => $data, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage))]];
    }

    /** @param \Illuminate\Support\Collection<int,object> $tasks */
    private function syncState(\Illuminate\Support\Collection $tasks, ?int $storeId, bool $shared): ?array
    {
        if (! $shared) {
            $tasks = $tasks->where('marketplace_store_id', $storeId);
        }
        if ($tasks->isEmpty()) {
            return null;
        }
        $priority = ['uncertain' => 6, 'auth_expired' => 5, 'failed' => 4, 'processing' => 3, 'queued' => 2, 'waiting_source' => 2, 'confirmed' => 1, 'superseded' => 0, 'not_applicable' => 0];
        $worst = $tasks->sortByDesc(fn ($task) => $priority[$task->status] ?? 3)->first();

        return ['id' => $worst->id, 'status' => $worst->status, 'tasks' => $tasks->map(fn ($task) => (array) $task)->all()];
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
