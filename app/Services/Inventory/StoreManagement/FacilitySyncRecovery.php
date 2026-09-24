<?php

namespace App\Services\Inventory\StoreManagement;

use App\Contracts\StoreInventoryPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exact-location status and read-only reconciliation for a counted facility SKU. */
final class FacilitySyncRecovery
{
    public function details(int $storeId, int $facilityId, int $variantId): array
    {
        $c = $this->context($storeId, $facilityId, $variantId);
        $task = $this->tasks($storeId, $facilityId, $variantId)
            ->where('status', '!=', 'superseded')
            ->orderByRaw("CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END")
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        $receipt = $task ? json_decode($task->provider_receipt ?? '{}', true) ?: [] : [];
        $quantity = $c['balance'] && $c['balance']->counted_at !== null
            && $c['balance']->physical_stock !== null && $c['balance']->reserved_stock !== null
            && $c['balance']->safety_stock !== null
            ? max(0, (int) $c['balance']->physical_stock - (int) $c['balance']->reserved_stock
                - (int) $c['balance']->safety_stock) : null;
        $observed = isset($receipt['observed_quantity']) ? (int) $receipt['observed_quantity'] : null;
        $status = match ($task?->status) {
            'confirmed' => $observed === $quantity && ($receipt['provider_location_id'] ?? null)
                === $c['facility']->provider_facility_id ? 'synced' : 'not_synced',
            'processing', 'facility_pending', 'uncertain' => 'awaiting_confirmation',
            'auth_expired' => 'auth_expired',
            'failed', 'facility_blocked' => 'sync_failed',
            default => 'not_synced',
        };
        if ($c['scan']?->status !== 'verified' || ! $c['facility']->is_active
            || ! $c['facility']->inventory_warehouse_id) {
            $status = 'verification_pending';
        }
        $canCheck = $status !== 'verification_pending' && (bool) $c['listing'];
        $canResync = $canCheck && $quantity !== null
            && ! in_array($task?->status, ['processing', 'uncertain'], true)
            && config('marketplace.multiwarehouse_stock_sync_enabled') === true;

        return ['store_id' => $storeId, 'facility_id' => $facilityId, 'variant_id' => $variantId,
            'provider_location_id' => $c['facility']->provider_facility_id,
            'status' => $status, 'jw_available_stock' => $quantity,
            'marketplace_reported_stock' => $observed,
            'difference' => $quantity === null || $observed === null ? null : $quantity - $observed,
            'last_checked_at' => $receipt['observed_at'] ?? $receipt['confirmed_at'] ?? null,
            'task_id' => $task?->id, 'task_status' => $task?->status,
            'can_check' => $canCheck, 'can_resync' => $canResync];
    }

    public function check(int $storeId, int $facilityId, int $variantId,
        StoreInventoryPublisher $publisher): array
    {
        $c = $this->context($storeId, $facilityId, $variantId);
        if ($c['scan']?->status !== 'verified' || ! $c['facility']->is_active
            || ! $c['facility']->inventory_warehouse_id || ! $c['listing']) {
            $this->reject('Verify the exact facility and SKU mapping before checking stock.');
        }
        $listing = $c['listing'];
        $listing->sync_provider_location_id = $c['facility']->provider_facility_id;
        $listing->sync_strict_facility = true;
        $observation = $publisher->observe($c['store'], $listing);
        if (($observation['provider_location_id'] ?? null) !== $c['facility']->provider_facility_id
            || ! is_int($observation['observed_quantity'] ?? null)) {
            $this->reject('The provider did not return an exact location stock count.');
        }
        DB::transaction(function () use ($storeId, $facilityId, $variantId, $observation): void {
            $task = $this->tasks($storeId, $facilityId, $variantId)->orderByDesc('created_at')
                ->orderByDesc('id')->lockForUpdate()->first();
            if (! $task || $task->status === 'processing') {
                return;
            }
            $balance = DB::table('inventory_balances')->find($task->inventory_balance_id);
            $matches = $balance && (int) $balance->version === (int) $task->balance_version
                && (int) $task->target_quantity === $observation['observed_quantity'];
            DB::table('inventory_store_sync_tasks')->where('id', $task->id)->update([
                'status' => $matches ? 'confirmed' : ($task->status === 'confirmed' ? 'failed' : $task->status),
                'provider_receipt' => json_encode($observation + [
                    'confirmation_method' => 'provider_readback',
                ], JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
        }, 3);

        return $this->details($storeId, $facilityId, $variantId);
    }

    public function resync(int $storeId, int $facilityId, int $variantId, string $operationKey): array
    {
        if (! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            $this->reject('Use a valid operation key.');
        }
        if (config('marketplace.multiwarehouse_stock_sync_enabled') !== true) {
            $this->reject('Facility stock sync is not enabled.');
        }
        $c = $this->context($storeId, $facilityId, $variantId);
        if ($c['scan']?->status !== 'verified' || ! $c['facility']->is_active
            || ! $c['facility']->inventory_warehouse_id || ! $c['balance']
            || $c['balance']->counted_at === null || ! $c['listing']) {
            $this->reject('Verify the exact counted facility and SKU mapping first.');
        }
        $existing = $this->tasks($storeId, $facilityId, $variantId)
            ->where('event_key', 'facility-resync:'.$operationKey)->first();
        if ($existing) {
            return $this->details($storeId, $facilityId, $variantId);
        }
        if ($this->tasks($storeId, $facilityId, $variantId)
            ->whereIn('status', ['processing', 'uncertain'])->exists()) {
            $this->reject('Check the uncertain provider result before sending another stock update.');
        }
        app(SyncOutbox::class)->enqueueFacilityBalance('facility-resync:'.$operationKey,
            $c['balance'], $c['facility']);

        return $this->details($storeId, $facilityId, $variantId);
    }

    private function context(int $storeId, int $facilityId, int $variantId): array
    {
        $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
        $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)
            ->where('marketplace_store_id', $storeId)->first();
        abort_unless($store && $facility && DB::table('product_variants')->where('id', $variantId)->exists(), 404);
        $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->first();
        $balance = $facility->inventory_warehouse_id ? DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'facility')->where('pool.warehouse_id', $facility->inventory_warehouse_id)
            ->where('balance.product_variant_id', $variantId)->first(['balance.*']) : null;
        $listing = DB::table('marketplace_variant_mappings as mapping')
            ->join('marketplace_listing_variants as listing', 'listing.id', '=', 'mapping.listing_variant_id')
            ->where('mapping.store_id', $storeId)->where('listing.store_id', $storeId)
            ->where('mapping.product_variant_id', $variantId)->first(['listing.*']);

        return compact('store', 'facility', 'scan', 'balance', 'listing');
    }

    private function tasks(int $storeId, int $facilityId, int $variantId)
    {
        return DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $storeId)
            ->where('marketplace_store_facility_id', $facilityId)
            ->where('product_variant_id', $variantId);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['sync' => $message]);
    }
}
