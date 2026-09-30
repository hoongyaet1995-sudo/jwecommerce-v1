<?php

namespace App\Services\Inventory\StoreManagement;

use App\Jobs\ProcessBulkMarketplaceStockRetrieval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BulkMarketplaceStockRetrieval
{
    /** @param list<int> $variantIds */
    public function start(User $actor, array $variantIds): array
    {
        $this->authorize($actor);
        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));
        if ($variantIds === [] || count($variantIds) > 250 || min($variantIds) < 1) {
            throw ValidationException::withMessages(['variant_ids' => 'Select 1 to 250 SKUs.']);
        }

        return DB::transaction(function () use ($actor, $variantIds): array {
            $config = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! config('inventory.current_stock_only') || $config?->operating_mode !== 'independent_stores'
                || $config->active_rebaseline_epoch_id !== null) {
                throw ValidationException::withMessages(['inventory' => 'Independent Current Stock mode is required.']);
            }
            if (DB::table('marketplace_stock_retrieval_runs')->whereIn('status', ['queued', 'running'])->exists()) {
                throw ValidationException::withMessages(['run' => 'A stock retrieval is already running. Open its progress first.']);
            }
            $known = DB::table('product_variants')->whereIn('id', $variantIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($known) !== count($variantIds)) {
                throw ValidationException::withMessages(['variant_ids' => 'One or more selected SKUs no longer exist.']);
            }
            $runId = (string) Str::uuid();
            $now = now();
            $items = [];
            $mappings = DB::table('marketplace_variant_mappings as mapping')
                ->join('marketplace_stores as store', 'store.id', '=', 'mapping.store_id')
                ->whereIn('mapping.product_variant_id', $variantIds)
                ->whereIn('store.platform', ['lazada', 'shopee'])
                ->get(['mapping.product_variant_id', 'mapping.store_id'])->unique(fn ($row) => $row->product_variant_id.':'.$row->store_id)
                ->groupBy('product_variant_id');
            foreach ($variantIds as $variantId) {
                $stores = $mappings->get($variantId, collect());
                if ($stores->isEmpty()) {
                    $items[] = $this->item($runId, $variantId, null, null, 'unmapped', $now);
                    continue;
                }
                foreach ($stores as $mapped) {
                    $storeId = (int) $mapped->store_id;
                    $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->first();
                    if ($scan?->status === 'verified' && $scan->topology === 'no_warehouse') {
                        $items[] = $this->item($runId, $variantId, $storeId, null, 'store:'.$storeId, $now);
                        continue;
                    }
                    $facilities = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
                        ->where('is_active', true)->orderBy('id')->get();
                    if ($facilities->isEmpty()) {
                        $items[] = $this->item($runId, $variantId, $storeId, null, 'unverified:'.$storeId, $now);
                        continue;
                    }
                    foreach ($facilities as $facility) {
                        $items[] = $this->item($runId, $variantId, $storeId, (int) $facility->id,
                            'facility:'.$facility->id, $now);
                    }
                }
            }
            DB::table('marketplace_stock_retrieval_runs')->insert([
                'id' => $runId, 'actor_id' => $actor->id, 'status' => 'queued',
                'selected_skus' => count($variantIds), 'total_locations' => count($items),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (array_chunk($items, 100) as $chunk) {
                DB::table('marketplace_stock_retrieval_items')->insert($chunk);
            }
            ProcessBulkMarketplaceStockRetrieval::dispatch($runId)->afterCommit();

            return $this->status($actor, $runId);
        }, 3);
    }

    public function latest(User $actor): ?array
    {
        $this->authorize($actor);
        $id = DB::table('marketplace_stock_retrieval_runs')->orderByDesc('created_at')->value('id');
        return $id ? $this->status($actor, $id) : null;
    }

    public function status(User $actor, string $runId): array
    {
        $this->authorize($actor);
        $run = DB::table('marketplace_stock_retrieval_runs')->where('id', $runId)->first();
        abort_unless($run, 404);
        $items = DB::table('marketplace_stock_retrieval_items as item')
            ->join('product_variants as variant', 'variant.id', '=', 'item.product_variant_id')
            ->leftJoin('marketplace_stores as store', 'store.id', '=', 'item.marketplace_store_id')
            ->leftJoin('marketplace_store_facilities as facility', 'facility.id', '=', 'item.marketplace_store_facility_id')
            ->where('item.run_id', $runId)->orderBy('item.id')
            ->get(['item.id', 'variant.sku', 'item.product_variant_id', 'store.shop_name as store_name',
                'facility.name as facility_name', 'item.status', 'item.action', 'item.observed_quantity',
                'item.previous_quantity', 'item.error_code', 'item.attempts', 'item.observed_at']);
        $successfulSkus = $items->groupBy('product_variant_id')
            ->filter(fn ($group) => $group->every(fn ($item) => in_array($item->status, ['succeeded', 'skipped'], true)))->count();
        $completed = $items->filter(fn ($item) => in_array($item->status, ['succeeded', 'skipped'], true))->count();
        $skipped = $items->where('status', 'skipped')->count();
        $failed = $items->where('status', 'failed')->count();

        return ['id' => $run->id, 'status' => $run->status, 'selected_skus' => (int) $run->selected_skus,
            'can_resume' => in_array($run->status, ['paused', 'completed_with_errors'], true)
                || (in_array($run->status, ['queued', 'running'], true)
                    && $run->updated_at < now()->subMinutes(25)->toDateTimeString()),
            'completed_skus' => $successfulSkus, 'remaining_skus' => (int) $run->selected_skus - $successfulSkus,
            'total_locations' => (int) $run->total_locations, 'completed_locations' => $completed,
            'failed_locations' => $failed, 'skipped_locations' => $skipped,
            'last_error_code' => $run->last_error_code,
            'heartbeat_at' => $run->heartbeat_at, 'updated_at' => $run->updated_at,
            'finished_at' => $run->finished_at,
            'items' => $items->map(fn ($item) => (array) $item)->all()];
    }

    public function resume(User $actor, string $runId): array
    {
        $this->authorize($actor);
        DB::transaction(function () use ($runId): void {
            $run = DB::table('marketplace_stock_retrieval_runs')->where('id', $runId)->lockForUpdate()->first();
            abort_unless($run, 404);
            $stale = in_array($run->status, ['queued', 'running'], true)
                && $run->updated_at < now()->subMinutes(25)->toDateTimeString();
            if (! in_array($run->status, ['paused', 'completed_with_errors'], true) && ! $stale) {
                throw ValidationException::withMessages(['run' => 'Only a paused or timed-out retrieval can resume.']);
            }
            DB::table('marketplace_stock_retrieval_items')->where('run_id', $runId)
                ->whereIn('status', ['failed', 'processing'])->update(['status' => 'pending', 'error_code' => null, 'updated_at' => now()]);
            DB::table('marketplace_stock_retrieval_runs')->where('id', $runId)->update([
                'status' => 'queued', 'failed_locations' => 0, 'last_error_code' => null,
                'finished_at' => null, 'heartbeat_at' => now(), 'updated_at' => now(),
            ]);
            ProcessBulkMarketplaceStockRetrieval::dispatch($runId)->afterCommit();
        }, 3);

        return $this->status($actor, $runId);
    }

    private function item(string $runId, int $variantId, ?int $storeId, ?int $facilityId,
        string $locationKey, mixed $now): array
    {
        return ['run_id' => $runId, 'product_variant_id' => $variantId,
            'marketplace_store_id' => $storeId, 'marketplace_store_facility_id' => $facilityId,
            'location_key' => $locationKey, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now];
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->is_admin && ($actor->is_super_admin
            || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }
}
