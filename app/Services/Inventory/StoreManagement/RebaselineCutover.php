<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Archives old accounting owners and opens uncounted epoch owners after exact provider zeroes. */
final class RebaselineCutover
{
    public function __construct(private readonly RebaselineReadiness $readiness) {}

    public function review(User $actor, string $epochId): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($epochId): array {
            $epoch = DB::table('inventory_rebaseline_epochs')->where('id', $epochId)->lockForUpdate()->first();
            abort_unless($epoch, 404);
            $this->assertReady($epoch);
            $hash = $this->snapshotHash($epoch);
            DB::table('inventory_rebaseline_epochs')->where('id', $epochId)->update([
                'review_hash' => $hash, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);

            return ['epoch_id' => $epochId, 'hash' => $hash,
                'expires_at' => now()->addMinutes(10)->toIso8601String()];
        }, 3);
    }

    public function confirm(User $actor, string $epochId, string $reviewHash, string $operationKey): array
    {
        $this->authorize($actor);
        if (! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['operation_key' => 'Use a valid operation key.']);
        }

        return DB::transaction(function () use ($epochId, $reviewHash, $operationKey): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $epoch = DB::table('inventory_rebaseline_epochs')->where('id', $epochId)->lockForUpdate()->first();
            abort_unless($epoch, 404);
            if ($epoch->status === 'counting' && $epoch->cutover_operation_key === $operationKey) {
                return json_decode($epoch->cutover_receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($epoch->status !== 'frozen' || $epoch->review_hash !== $reviewHash
                || ! $epoch->reviewed_at || now()->gt(\Illuminate\Support\Carbon::parse($epoch->reviewed_at)->addMinutes(10))) {
                $this->reject('The reviewed cutover has expired or changed.');
            }
            $this->assertReady($epoch);
            if (! hash_equals($reviewHash, $this->snapshotHash($epoch))) {
                $this->reject('Inventory changed after the cutover review.');
            }

            $owners = $this->requiredOwners($epoch);
            $oldBalances = DB::table('inventory_balances')->orderBy('id')->lockForUpdate()->get();
            foreach ($oldBalances as $balance) {
                DB::table('inventory_rebaseline_legacy_balances')->insert([
                    'epoch_id' => $epochId, 'inventory_balance_id' => $balance->id,
                    'physical_stock' => $balance->physical_stock, 'reserved_stock' => $balance->reserved_stock,
                    'safety_stock' => $balance->safety_stock, 'counted_at' => $balance->counted_at,
                    'balance_version' => $balance->version, 'archived_at' => now(),
                ]);
            }
            foreach ($owners as $owner) {
                [$warehouseId, $variantId] = $owner;
                DB::table('inventory_pools')->insertOrIgnore([
                    'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility',
                    'warehouse_id' => $warehouseId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $pool = DB::table('inventory_pools')->where('kind', 'facility')
                    ->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
                if (! $pool) {
                    $this->reject('The physical facility owner is unavailable.');
                }
                DB::table('inventory_balances')->insertOrIgnore([
                    'inventory_pool_id' => $pool->id, 'product_variant_id' => $variantId,
                    'rebaseline_epoch_id' => $epochId, 'version' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $newBalance = DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                    ->where('product_variant_id', $variantId)->lockForUpdate()->first();
                if ($newBalance->counted_at !== null || $newBalance->physical_stock !== null
                    || ($newBalance->rebaseline_epoch_id !== null && $newBalance->rebaseline_epoch_id !== $epochId)) {
                    $this->reject('A new facility balance is already owned or counted.');
                }
                DB::table('inventory_balances')->where('id', $newBalance->id)->update([
                    'rebaseline_epoch_id' => $epochId, 'updated_at' => now(),
                ]);
            }
            DB::table('inventory_configuration')->where('id', 1)->whereNull('active_rebaseline_epoch_id')
                ->update(['active_rebaseline_epoch_id' => $epochId,
                    'version' => (int) $configuration->version + 1]);
            $receipt = ['active_epoch_id' => $epochId, 'status' => 'counting',
                'legacy_balance_count' => $oldBalances->count(), 'uncounted_owner_count' => count($owners)];
            DB::table('inventory_rebaseline_epochs')->where('id', $epochId)->update([
                'status' => 'counting', 'cutover_operation_key' => $operationKey,
                'cutover_receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'confirmed_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function assertReady(object $epoch): void
    {
        if ($epoch->status !== 'frozen') {
            $this->reject('The inventory freeze is no longer current.');
        }
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        if (! $configuration || $configuration->active_rebaseline_epoch_id !== null
            || (int) $configuration->version !== (int) $epoch->configuration_version) {
            $this->reject('Inventory configuration changed after freeze.');
        }
        $preflight = $this->readiness->inspect();
        if (! $preflight['ready']) {
            $this->reject('Inventory or provider readiness changed after freeze.');
        }
        $snapshot = json_decode($epoch->review_snapshot ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        foreach ($snapshot['stores'] ?? [] as $store) {
            $current = DB::table('marketplace_store_facility_scans')
                ->where('marketplace_store_id', $store['store_id'])->first();
            if (! $current || $current->status !== 'verified'
                || (int) $current->revision !== (int) $store['topology_revision']) {
                $this->reject('A provider warehouse list changed after freeze.');
            }
        }
        if (DB::table('inventory_rebaseline_destinations')->where('epoch_id', $epoch->id)
            ->where('zero_status', '!=', 'zero_confirmed')->exists()) {
            $this->reject('Every provider destination must have an exact zero readback.');
        }
        $this->requiredOwners($epoch);
    }

    /** @return list<array{int,int}> */
    private function requiredOwners(object $epoch): array
    {
        $website = DB::table('inventory_fulfillment_routes as route')
            ->join('inventory_warehouses as warehouse', 'warehouse.id', '=', 'route.warehouse_id')
            ->where('route.channel_key', 'website')->where('route.is_enabled', true)
            ->where('warehouse.status', 'active')->where('warehouse.is_active', true)
            ->value('route.warehouse_id');
        if (! $website) {
            $this->reject('An active JW Storefront physical route is required.');
        }
        $owners = [];
        foreach (DB::table('product_variants')->orderBy('id')->pluck('id') as $variantId) {
            $owners[$website.':'.$variantId] = [(int) $website, (int) $variantId];
        }
        foreach (DB::table('inventory_rebaseline_destinations')->where('epoch_id', $epoch->id)->orderBy('id')->get() as $destination) {
            $warehouse = $destination->provider_facility_id === null
                ? DB::table('inventory_fulfillment_routes')->where('channel_key', 'marketplace:'.$destination->store_id)
                    ->where('is_enabled', true)->value('warehouse_id')
                : DB::table('marketplace_store_facilities')->where('id', $destination->provider_facility_id)
                    ->where('marketplace_store_id', $destination->store_id)->where('is_active', true)
                    ->where('provider_facility_id', $destination->route_key)->value('inventory_warehouse_id');
            if (! $warehouse || ! DB::table('inventory_warehouses')->where('id', $warehouse)
                ->where('status', 'active')->where('is_active', true)->exists()) {
                $this->reject('A provider destination has no verified JW physical route.');
            }
            $owners[$warehouse.':'.$destination->variant_id] = [(int) $warehouse, (int) $destination->variant_id];
        }

        return array_values($owners);
    }

    private function snapshotHash(object $epoch): string
    {
        $balances = DB::table('inventory_balances')->orderBy('id')->get([
            'id', 'version', 'physical_stock', 'reserved_stock', 'safety_stock', 'counted_at',
        ])->map(fn (object $balance): array => (array) $balance)->all();
        $destinations = DB::table('inventory_rebaseline_destinations')->where('epoch_id', $epoch->id)
            ->orderBy('id')->get(['id', 'zero_status', 'zero_confirmed_at', 'route_key'])
            ->map(fn (object $destination): array => (array) $destination)->all();

        return hash('sha256', json_encode([$balances, $destinations, $this->requiredOwners($epoch)], JSON_THROW_ON_ERROR));
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && $actor->is_admin && $actor->is_super_admin, 403);
        abort_unless(config('marketplace.rebaseline_cutover_enabled') === true, 404);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['rebaseline' => $message]);
    }
}
