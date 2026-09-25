<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OverviewReader
{
    public function __construct(
        private readonly ModeCatalogReader $modeCatalog,
        private readonly StoreEnrollmentService $enrollment,
    ) {}

    /** @return array<string,mixed> */
    public function read(array $filters = []): array
    {
        $this->enrollment->ensureWebsite();
        $this->enrollment->enrollConnectedStores();
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        $mode = $configuration?->operating_mode === 'independent_stores' ? 'independent' : 'shared';
        $modeCatalog = $this->modeCatalog->read();
        $readiness = collect($modeCatalog['items'])->flatMap(fn (array $item) => collect($item['mapped_stores'])
            ->mapWithKeys(fn (array $store): array => [((int) $item['variant_id']).':'.((int) $store['id']) => $store['sync_readiness']]));
        $query = DB::table('inventory_balances as b')
            ->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
            ->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')
            ->join('products as product', 'product.id', '=', 'v.product_id')
            ->leftJoin('marketplace_stores as store', 'store.id', '=', 'p.marketplace_store_id')
            ->leftJoinSub(DB::table('marketplace_store_facilities')
                ->selectRaw('MIN(id) as id, inventory_warehouse_id')
                ->whereNotNull('inventory_warehouse_id')->where('is_active', true)
                ->groupBy('inventory_warehouse_id'), 'facility_owner', function ($join): void {
                $join->on('facility_owner.inventory_warehouse_id', '=', 'p.warehouse_id')
                    ->where('p.kind', '=', 'facility');
            })
            ->leftJoin('marketplace_store_facilities as facility', function ($join): void {
                $join->on('facility.id', '=', 'facility_owner.id')
                    ->where('p.kind', '=', 'facility');
            })
            ->leftJoin('marketplace_stores as facility_store', 'facility_store.id', '=', 'facility.marketplace_store_id')
            ->leftJoin('marketplace_variant_mappings as mapping', function ($join): void {
                $join->on('mapping.product_variant_id', '=', 'b.product_variant_id')
                    ->on('mapping.store_id', '=', 'p.marketplace_store_id');
            })
            ->select(['b.id', 'b.product_variant_id', 'b.physical_stock', 'b.reserved_stock', 'b.safety_stock',
                'b.counted_at', 'b.version', 'p.pool_key', 'p.kind', 'p.marketplace_store_id',
                'facility.id as facility_id', 'facility.provider_facility_id', 'facility.marketplace_store_id as facility_store_id',
                'facility_store.platform as facility_platform', 'facility_store.shop_name as facility_store_name',
                'mapping.id as mapping_id', 'mapping.listing_variant_id',
                'store.platform', 'store.shop_name', 'store.status as connection_status', 'v.sku', 'v.title as variant_title',
                'product.title as product_title']);
        $activeEpochId = $configuration?->active_rebaseline_epoch_id;
        if ($activeEpochId !== null) {
            $query->where('b.rebaseline_epoch_id', $activeEpochId)->where('p.kind', 'facility');
        }
        if (! empty($filters['platform'])) {
            $query->where(function ($query) use ($filters): void {
                $query->where('store.platform', $filters['platform'])
                    ->orWhere('facility_store.platform', $filters['platform']);
            });
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
        $rows = $balanceRows->map(function (object $row) use ($syncTasks, $readiness, $mode, $activeEpochId): array {
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
                    : ($row->kind === 'facility' ? ($row->facility_id === null ? 'website' : 'facility:'.$row->facility_id)
                    : ($row->marketplace_store_id ? 'marketplace:'.$row->marketplace_store_id : 'website')),
                'store_id' => $row->kind === 'facility' ? (int) $row->facility_store_id
                    : ($row->marketplace_store_id === null ? null : (int) $row->marketplace_store_id),
                'store_name' => $row->kind === 'facility' ? $row->facility_store_name : $row->shop_name,
                'platform' => $row->kind === 'facility' ? $row->facility_platform : ($row->platform ?? 'website'),
                'facility_id' => $row->facility_id === null ? null : (int) $row->facility_id,
                'provider_facility_id' => $row->provider_facility_id,
                'pool_kind' => $row->kind,
                'physical' => $physical,
                'reserved' => $reserved,
                'available' => $available,
                'counted' => $row->counted_at !== null,
                'allocation_eligible' => $activeEpochId !== null ? $row->kind === 'facility'
                    : ($mode === 'shared'
                        ? $row->kind === 'shared'
                        : ($row->kind === 'store' && ($row->marketplace_store_id === null || $row->mapping_id !== null))
                            || ($row->kind === 'facility' && $row->facility_id !== null)),
                'version' => (int) $row->version,
                'provider_observation' => null,
                'sync_readiness' => $row->marketplace_store_id === null ? null
                    : $readiness->get(((int) $row->product_variant_id).':'.((int) $row->marketplace_store_id)),
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
            'totals' => ['known' => $known, 'unknown_accounts' => $unknown], 'mode_catalog' => $modeCatalog,
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

        return $query->orderBy('s.id')->get(['s.id', 's.platform', 's.shop_name', 's.region', 's.status', 's.account_mode',
            'a.management_status', 'a.version as management_version'])->map(fn (object $store): array => [
                'id' => (int) $store->id,
                'platform' => $store->platform,
                'account_mode' => $store->account_mode,
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
        $rows = $query->orderByDesc('o.created_at')->orderByDesc('o.id')
            ->forPage($page, $perPage)->get(['o.id', 'o.review_id', 'o.kind', 'o.ledger_status', 'o.receipt', 'r.payload',
                'r.before as review_before', 'r.after as review_after', 'o.created_at', 'u.name as actor_name']);
        $payloads = $rows->mapWithKeys(fn (object $row): array => [
            $row->id => $row->payload ? json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR) : [],
        ]);
        $balanceIds = $rows->flatMap(function (object $row) use ($payloads): array {
            $payload = $payloads[$row->id] ?? [];
            $ids = match ($row->kind) {
                'transfer' => [(int) ($payload['source_balance_id'] ?? 0), (int) ($payload['destination_balance_id'] ?? 0)],
                'allocation' => array_map('intval', array_values($payload['balance_ids'] ?? [])),
                default => [(int) ($payload['balance_id'] ?? 0)],
            };
            return array_values(array_filter($ids));
        })->unique()->values();
        $balances = $balanceIds->isEmpty() ? collect() : DB::table('inventory_balances as b')
            ->join('inventory_pools as pool', 'pool.id', '=', 'b.inventory_pool_id')
            ->join('product_variants as variant', 'variant.id', '=', 'b.product_variant_id')
            ->join('products as product', 'product.id', '=', 'variant.product_id')
            ->leftJoin('marketplace_stores as store', 'store.id', '=', 'pool.marketplace_store_id')
            ->whereIn('b.id', $balanceIds)
            ->get(['b.id', 'variant.sku', 'variant.title as variant_name', 'product.title as product_name',
                'pool.marketplace_store_id', 'store.shop_name', 'store.platform'])
            ->keyBy('id');
        $variantIds = $payloads->pluck('variant_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $variants = $variantIds->isEmpty() ? collect() : DB::table('product_variants as variant')
            ->join('products as product', 'product.id', '=', 'variant.product_id')->whereIn('variant.id', $variantIds)
            ->get(['variant.id', 'variant.sku', 'variant.title as variant_name', 'product.title as product_name'])->keyBy('id');
        $storeIds = $payloads->pluck('store_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $activityStores = $storeIds->isEmpty() ? collect() : DB::table('marketplace_stores')->whereIn('id', $storeIds)
            ->get(['id', 'shop_name', 'platform'])->keyBy('id');
        $stages = DB::table('inventory_store_sync_tasks')->whereIn('operation_id', $rows->pluck('id'))
            ->orderBy('created_at')->get()->groupBy('operation_id');
        $data = $rows->map(function (object $row) use ($payloads, $balances, $variants, $activityStores, $stages): array {
            $payload = $payloads[$row->id] ?? [];
            $before = $row->review_before ? json_decode($row->review_before, true, 512, JSON_THROW_ON_ERROR) : [];
            $after = $row->review_after ? json_decode($row->review_after, true, 512, JSON_THROW_ON_ERROR) : [];
            $transfer = null;
            $activityBalance = $balances->get((int) ($payload['balance_id'] ?? 0));
            $store = static fn (?object $balance): array => $balance?->marketplace_store_id
                ? ['id' => (string) $balance->marketplace_store_id, 'name' => $balance->shop_name ?: 'Unavailable', 'platform' => $balance->platform ?: 'webstore']
                : ['id' => 'website', 'name' => 'JW Ecom Storefront', 'platform' => 'webstore'];
            if ($row->kind === 'transfer') {
                $source = $balances->get((int) ($payload['source_balance_id'] ?? 0));
                $destination = $balances->get((int) ($payload['destination_balance_id'] ?? 0));
                $identity = $source ?? $destination;
                $transfer = [
                    'master_sku' => $identity?->sku ?? 'Unavailable',
                    'product_name' => $identity?->product_name ?? 'Unavailable',
                    'variant_name' => $identity?->variant_name ?? 'Unavailable',
                    'source_store' => $store($source),
                    'destination_store' => $store($destination),
                    'quantity' => (int) ($payload['quantity'] ?? 0),
                    'reason' => (string) ($payload['reason'] ?? 'Store transfer'),
                ];
                $activity = [
                    'master_sku' => $identity?->sku ?? 'Unavailable', 'product_name' => $identity?->product_name ?? 'Unavailable',
                    'variant_name' => $identity?->variant_name ?? null, 'store' => null,
                    'source_store' => $store($source), 'destination_store' => $store($destination),
                    'previous_stock' => $before['source']['physical'] ?? null, 'resulting_stock' => $after['source']['physical'] ?? null,
                    'quantity_change' => (int) ($payload['quantity'] ?? 0),
                    'reason' => (string) ($payload['reason'] ?? 'Store transfer'), 'notes' => $payload['notes'] ?? null,
                ];
            } elseif ($row->kind === 'allocation') {
                $allocationIds = array_values($payload['balance_ids'] ?? []);
                $identity = $balances->get((int) ($allocationIds[0] ?? 0));
                $accounts = $before['accounts'] ?? [];
                $uncounted = $accounts !== [] && collect($accounts)->every(fn (array $account): bool => ($account['physical'] ?? null) === null);
                $activity = [
                    'master_sku' => $identity?->sku ?? 'Unavailable', 'product_name' => $identity?->product_name ?? 'Unavailable',
                    'variant_name' => $identity?->variant_name ?? null,
                    'store' => ['id' => 'multiple', 'name' => count($payload['allocations'] ?? []).' store accounts', 'platform' => 'multiple'],
                    'source_store' => null, 'destination_store' => null,
                    'previous_stock' => $uncounted ? 'Not Counted' : ($before['total_physical'] ?? null),
                    'resulting_stock' => $after['total_physical'] ?? ($payload['physical_count'] ?? null),
                    'quantity_change' => (int) ($payload['physical_count'] ?? 0),
                    'reason' => (string) ($payload['reason'] ?? 'Initial store count'), 'notes' => $payload['notes'] ?? null,
                ];
            } elseif ($row->kind === 'platform_sync') {
                $identity = $variants->get((int) ($payload['variant_id'] ?? 0));
                $syncStore = $activityStores->get((int) ($payload['store_id'] ?? 0));
                $activity = [
                    'master_sku' => $identity?->sku ?? ($payload['sku'] ?? 'Unavailable'),
                    'product_name' => $identity?->product_name ?? 'Unavailable', 'variant_name' => $identity?->variant_name ?? null,
                    'store' => $syncStore ? ['id' => (string) $syncStore->id, 'name' => $syncStore->shop_name, 'platform' => $syncStore->platform] : null,
                    'source_store' => null, 'destination_store' => null,
                    'previous_stock' => $before['quantity'] ?? $payload['quantity'] ?? null,
                    'resulting_stock' => $after['quantity'] ?? $payload['quantity'] ?? null,
                    'quantity_change' => 0, 'reason' => (string) ($payload['reason'] ?? 'Resync latest stock'),
                    'notes' => $payload['notes'] ?? null,
                ];
            } else {
                $previous = $before['physical'] ?? null;
                $resulting = $after['physical'] ?? null;
                $activity = [
                    'master_sku' => $activityBalance?->sku ?? 'Unavailable', 'product_name' => $activityBalance?->product_name ?? 'Unavailable',
                    'variant_name' => $activityBalance?->variant_name ?? null, 'store' => $store($activityBalance),
                    'source_store' => null, 'destination_store' => null,
                    'previous_stock' => $previous, 'resulting_stock' => $resulting,
                    'quantity_change' => is_numeric($previous) && is_numeric($resulting) ? (int) $resulting - (int) $previous : 0,
                    'reason' => (string) ($payload['reason'] ?? $row->kind), 'notes' => $payload['notes'] ?? null,
                ];
            }
            return [
                'id' => $row->id, 'review_id' => $row->review_id, 'kind' => $row->kind,
                'ledger_status' => $row->ledger_status, 'actor' => $row->actor_name,
                'created_at' => $row->created_at,
                'result' => [...$this->sanitize(json_decode($row->receipt, true, 512, JSON_THROW_ON_ERROR)),
                    'activity' => $activity,
                    'transfer' => $transfer,
                    'external_stages' => ($stages->get($row->id) ?? collect())->map(fn ($task) => (array) $task)->all()],
            ];
        })->all();

        return ['data' => $data, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage))]];
    }

    /** @param Collection<int,object> $tasks */
    private function syncState(Collection $tasks, ?int $storeId, bool $shared): ?array
    {
        if (! $shared) {
            $tasks = $tasks->where('marketplace_store_id', $storeId);
        }
        if ($tasks->isEmpty()) {
            return null;
        }
        $priority = ['uncertain' => 6, 'auth_expired' => 5, 'failed' => 4, 'processing' => 3, 'queued' => 2, 'waiting_source' => 2, 'confirmed' => 1, 'superseded' => 0, 'not_applicable' => 0];
        $worst = $tasks->sortByDesc(fn ($task) => $priority[$task->status] ?? 3)->first();

        return ['id' => $worst->id, 'status' => $worst->status,
            'tasks' => $tasks->map(fn ($task) => (array) $task)->values()->all()];
    }

    /** @return array<string,mixed> */
    public function operation(string $id): array
    {
        $operation = DB::table('inventory_store_operations')->where('id', $id)->first();
        abort_unless($operation, 404);
        $receipt = $this->sanitize(json_decode($operation->receipt, true, 512, JSON_THROW_ON_ERROR));
        $reviewPayload = $operation->review_id
            ? DB::table('inventory_store_reviews')->where('id', $operation->review_id)->value('payload')
            : null;
        $reviewInput = $reviewPayload ? json_decode($reviewPayload, true, 512, JSON_THROW_ON_ERROR) : [];
        $stages = DB::table('inventory_store_sync_tasks')->where('operation_id', $id)->orderBy('created_at')->get()
            ->map(fn (object $stage): array => ['id' => $stage->id, 'store_id' => $stage->marketplace_store_id,
                'variant_id' => (int) $stage->product_variant_id, 'stage' => $stage->stage, 'status' => $stage->status,
                'target_quantity' => $stage->target_quantity, 'attempts' => (int) $stage->attempts,
                'provider_receipt' => $stage->provider_receipt ? $this->sanitize(json_decode($stage->provider_receipt, true, 512, JSON_THROW_ON_ERROR)) : null]);

        return [...$receipt,
            'transfer_quantity' => $operation->kind === 'transfer' ? (int) ($reviewInput['quantity'] ?? 0) : null,
            'external_stages' => $stages->all()];
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
