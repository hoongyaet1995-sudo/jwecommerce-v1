<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FacilityLedgerMigration
{
    public function __construct(
        private readonly FacilityMigrationPreview $planner,
        private readonly FacilityRegistry $registry,
    ) {}

    /** @param array<string, int> $physicalByProviderId */
    public function review(User $actor, int $storeId, int $variantId, array $physicalByProviderId, string $reason): array
    {
        $this->guard($actor);
        $preview = $this->planner->preview($storeId, $variantId, $physicalByProviderId);
        if (! $preview['ready']) {
            throw ValidationException::withMessages(['facilities' => $preview['blockers']]);
        }
        $scan = $this->registry->read($storeId);
        $bindings = $this->bindings($scan);
        $configurationVersion = (int) DB::table('inventory_configuration')->where('id', 1)->value('version');
        $id = (string) Str::uuid();
        DB::table('marketplace_facility_ledger_migration_reviews')->insert([
            'id' => $id, 'marketplace_store_id' => $storeId, 'product_variant_id' => $variantId,
            'source_balance_id' => $preview['source']['balance_id'], 'actor_id' => $actor->id,
            'configuration_version' => $configurationVersion,
            'topology_revision' => $preview['topology_revision'], 'source_version' => $preview['source']['version'],
            'physical_by_provider_id' => json_encode($physicalByProviderId, JSON_THROW_ON_ERROR),
            'facility_bindings' => json_encode($bindings, JSON_THROW_ON_ERROR),
            'reason' => $reason, 'status' => 'pending', 'expires_at' => now()->addMinutes(10),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'status' => 'pending', 'store_id' => $storeId, 'variant_id' => $variantId,
            'source' => $preview['source'], 'allocated_physical' => $preview['allocated_physical'],
            'topology_revision' => $preview['topology_revision']];
    }

    public function confirm(User $actor, string $reviewId, string $operationKey): array
    {
        $this->guard($actor);

        return DB::transaction(function () use ($actor, $reviewId, $operationKey): array {
            $review = DB::table('marketplace_facility_ledger_migration_reviews')
                ->where('id', $reviewId)->lockForUpdate()->first();
            abort_unless($review, 404);
            if (DB::table('marketplace_facility_ledger_migration_reviews')->where('operation_key', $operationKey)
                ->where('id', '!=', $reviewId)->exists()) {
                $this->conflict('operation_key_reused');
            }
            if ($review->status === 'confirmed') {
                if ($review->operation_key !== $operationKey) {
                    $this->conflict('migration_already_confirmed');
                }

                return json_decode($review->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            if ((int) $review->actor_id !== (int) $actor->id && ! $actor->is_super_admin) {
                abort(403);
            }
            if ($review->status !== 'pending' || now()->gte($review->expires_at)) {
                $this->conflict('migration_review_expired');
            }
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || $configuration->operating_mode !== 'independent_stores'
                || (int) $configuration->version !== (int) $review->configuration_version) {
                $this->conflict('inventory_configuration_changed');
            }
            DB::table('product_variants')->where('id', $review->product_variant_id)->lockForUpdate()->first();
            $scan = DB::table('marketplace_store_facility_scans')
                ->where('marketplace_store_id', $review->marketplace_store_id)->lockForUpdate()->first();
            if (! $scan || $scan->status !== 'verified' || (int) $scan->revision !== (int) $review->topology_revision) {
                $this->conflict('facility_topology_changed');
            }
            $source = DB::table('inventory_balances')->where('id', $review->source_balance_id)->lockForUpdate()->first();
            if (! $source || (int) $source->version !== (int) $review->source_version
                || (int) $source->product_variant_id !== (int) $review->product_variant_id) {
                $this->conflict('source_balance_changed');
            }
            $quantities = json_decode($review->physical_by_provider_id, true, 512, JSON_THROW_ON_ERROR);
            $current = $this->registry->read((int) $review->marketplace_store_id);
            if ($this->bindings($current) !== json_decode($review->facility_bindings, true, 512, JSON_THROW_ON_ERROR)) {
                $this->conflict('facility_binding_changed');
            }
            $preview = $this->planner->preview((int) $review->marketplace_store_id,
                (int) $review->product_variant_id, $quantities);
            if (! $preview['ready'] || (int) $preview['source']['balance_id'] !== (int) $source->id) {
                $this->conflict('migration_conditions_changed');
            }

            $facilityBalances = [];
            foreach ($current['facilities'] as $facility) {
                $warehouseId = (int) $facility['inventory_warehouse_id'];
                DB::table('inventory_pools')->insertOrIgnore([
                    'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility',
                    'warehouse_id' => $warehouseId, 'marketplace_store_id' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $pool = DB::table('inventory_pools')->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
                if (! $pool || $pool->kind !== 'facility' || $pool->pool_key !== 'facility:'.$warehouseId) {
                    $this->conflict('physical_owner_changed');
                }
                $balance = DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                    ->where('product_variant_id', $review->product_variant_id)->lockForUpdate()->first();
                if ($balance && ($balance->counted_at !== null || $balance->physical_stock !== null
                    || $balance->reserved_stock !== null || $balance->safety_stock !== null)) {
                    $this->conflict('physical_balance_changed');
                }
                $quantity = (int) $quantities[$facility['provider_id']];
                $values = ['physical_stock' => $quantity, 'reserved_stock' => 0, 'safety_stock' => 0,
                    'counted_at' => now(), 'obligations_reconciled_at' => now(),
                    'version' => $balance ? (int) $balance->version + 1 : 1, 'updated_at' => now()];
                if ($balance) {
                    DB::table('inventory_balances')->where('id', $balance->id)->update($values);
                    $balanceId = (int) $balance->id;
                } else {
                    $balanceId = DB::table('inventory_balances')->insertGetId($values + [
                        'inventory_pool_id' => $pool->id, 'product_variant_id' => $review->product_variant_id,
                        'created_at' => now(),
                    ]);
                }
                $facilityBalances[] = ['provider_id' => $facility['provider_id'],
                    'warehouse_id' => $warehouseId, 'balance_id' => $balanceId, 'physical' => $quantity];
            }
            DB::table('inventory_balances')->where('id', $source->id)->update([
                'physical_stock' => 0, 'reserved_stock' => 0, 'safety_stock' => 0,
                'version' => (int) $source->version + 1, 'updated_at' => now(),
            ]);
            $receipt = ['review_id' => $reviewId, 'store_id' => (int) $review->marketplace_store_id,
                'variant_id' => (int) $review->product_variant_id, 'source_balance_id' => (int) $source->id,
                'source_physical_before' => (int) $source->physical_stock, 'source_physical_after' => 0,
                'facility_balances' => $facilityBalances, 'ledger_status' => 'applied',
                'provider_sync_status' => 'not_started'];
            DB::table('marketplace_facility_ledger_migration_reviews')->where('id', $reviewId)->update([
                'status' => 'confirmed', 'operation_key' => $operationKey,
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'confirmed_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function bindings(array $scan): array
    {
        $bindings = [];
        foreach ($scan['facilities'] as $facility) {
            $bindings[$facility['provider_id']] = $facility['inventory_warehouse_id'];
        }
        ksort($bindings);

        return $bindings;
    }

    private function guard(User $actor): void
    {
        abort_unless($actor->exists && $actor->is_admin && $actor->is_super_admin, 403);
        abort_unless(config('marketplace.multiwarehouse_migration_enabled') === true, 404);
    }

    private function conflict(string $code): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code, 'message' => 'Facility inventory changed after review. Review current stock again.',
        ]], 409));
    }
}
