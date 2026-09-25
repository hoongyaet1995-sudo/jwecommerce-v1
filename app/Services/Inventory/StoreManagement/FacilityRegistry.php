<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FacilityRegistry
{
    /** @param list<array{provider_id:string,name:string}> $facilities */
    public function confirm(int $storeId, array $facilities, bool $complete = true): array
    {
        if (! $complete) {
            $this->fail($storeId, 'incomplete_scan');
            throw new InvalidArgumentException('A complete provider facility scan is required.');
        }
        $normalized = [];
        foreach ($facilities as $facility) {
            $id = is_string($facility['provider_id'] ?? null) ? trim($facility['provider_id']) : '';
            $name = is_string($facility['name'] ?? null) ? trim($facility['name']) : '';
            if ($id === '' || strlen($id) > 120 || $name === '' || strlen($name) > 255 || isset($normalized[$id])) {
                $this->fail($storeId, 'invalid_facility_list');
                throw new InvalidArgumentException('Provider facility list is invalid or ambiguous.');
            }
            $normalized[$id] = $name;
        }

        DB::transaction(function () use ($storeId, $normalized): void {
            DB::table('marketplace_store_facility_scans')->insertOrIgnore([
                'marketplace_store_id' => $storeId, 'status' => 'verification_pending',
                'revision' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $scan = DB::table('marketplace_store_facility_scans')
                ->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            $existing = DB::table('marketplace_store_facilities')
                ->where('marketplace_store_id', $storeId)->get()->keyBy('provider_facility_id');
            $active = $existing->filter(fn (object $row): bool => (bool) $row->is_active);
            $changed = $scan->topology === null || $active->count() !== count($normalized);
            foreach ($normalized as $id => $name) {
                $old = $existing->get($id);
                if (! $old || ! $old->is_active || $old->name !== $name) {
                    $changed = true;
                }
                DB::table('marketplace_store_facilities')->updateOrInsert(
                    ['marketplace_store_id' => $storeId, 'provider_facility_id' => $id],
                    ['name' => $name, 'is_active' => true, 'observed_at' => now(), 'updated_at' => now()]
                );
            }
            $departed = array_diff($existing->keys()->all(), array_keys($normalized));
            if ($departed !== []) {
                DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
                    ->whereIn('provider_facility_id', $departed)->update(['is_active' => false, 'updated_at' => now()]);
            }
            DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->update([
                'status' => 'verified',
                'topology' => match (count($normalized)) {
                    0 => 'no_warehouse', 1 => 'single_warehouse', default => 'multi_warehouse',
                },
                'revision' => (int) $scan->revision + ($changed ? 1 : 0),
                'verified_at' => now(), 'last_error_code' => null, 'updated_at' => now(),
            ]);
            $this->ensureIndependentLazadaOwners($storeId);
        });

        return $this->read($storeId);
    }

    private function ensureIndependentLazadaOwners(int $storeId): void
    {
        if (config('marketplace.multiwarehouse_operations_enabled') !== true
            || DB::table('inventory_configuration')->where('id', 1)->value('operating_mode') !== 'independent_stores') {
            return;
        }
        $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
        if (! $store || $store->platform !== 'lazada' || $store->account_mode !== 'production') {
            return;
        }
        $facilities = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
            ->where('is_active', true)->whereNull('inventory_warehouse_id')->lockForUpdate()->get();
        foreach ($facilities as $facility) {
            $code = 'LZD-FAC-'.$storeId.'-'.$facility->id;
            DB::table('inventory_warehouses')->insertOrIgnore([
                'code' => $code, 'name' => 'Lazada '.$store->external_shop_id.' / '.$facility->name,
                'is_active' => true, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $warehouseId = DB::table('inventory_warehouses')->where('code', $code)->value('id');
            DB::table('marketplace_store_facilities')->where('id', $facility->id)
                ->whereNull('inventory_warehouse_id')->update([
                    'inventory_warehouse_id' => $warehouseId, 'updated_at' => now(),
                ]);
        }
    }

    public function fail(int $storeId, string $errorCode): void
    {
        DB::table('marketplace_store_facility_scans')->updateOrInsert(
            ['marketplace_store_id' => $storeId],
            ['status' => 'verification_pending', 'last_error_code' => $errorCode, 'updated_at' => now()]
        );
    }

    public function read(int $storeId): array
    {
        $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->first();
        $facilities = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
            ->where('is_active', true)->orderBy('provider_facility_id')
            ->get(['id', 'provider_facility_id', 'inventory_warehouse_id', 'name', 'observed_at'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id, 'provider_id' => $row->provider_facility_id,
                'inventory_warehouse_id' => $row->inventory_warehouse_id === null ? null : (int) $row->inventory_warehouse_id,
                'name' => $row->name, 'observed_at' => $row->observed_at,
            ])->all();

        return ['status' => $scan?->status ?? 'verification_pending',
            'topology' => $scan?->topology, 'revision' => (int) ($scan?->revision ?? 0),
            'ledger_migration_status' => $scan?->ledger_migration_status ?? 'pending',
            'stock_operations_ready' => $scan?->status === 'verified' && $scan?->ledger_migration_status === 'complete',
            'verified_at' => $scan?->verified_at, 'error_code' => $scan?->last_error_code,
            'facilities' => $facilities];
    }
}
