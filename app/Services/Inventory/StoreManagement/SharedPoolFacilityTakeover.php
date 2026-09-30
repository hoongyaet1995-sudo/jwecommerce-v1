<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A warehouse preference only; stock writes remain in the later sync outbox. */
final class SharedPoolFacilityTakeover
{
    public function set(int $storeId, int $facilityId, bool $enabled, User $actor): array
    {
        abort_unless($actor->is_admin && $actor->is_super_admin, 403);

        return DB::transaction(function () use ($storeId, $facilityId, $enabled): array {
            $mode = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->value('operating_mode');
            if ($mode !== 'shared_pool') {
                throw ValidationException::withMessages(['mode' => 'Warehouse takeover is available only in Shared Stock Pool mode.']);
            }
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            abort_unless($store && $store->platform === 'lazada', 404);
            $scan = DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $storeId)->first();
            if ($scan?->status !== 'verified' || ! in_array($scan?->topology, ['single_warehouse', 'multi_warehouse'], true)) {
                throw ValidationException::withMessages(['facility' => 'Verify this Lazada store warehouse list before changing takeover.']);
            }
            $facility = DB::table('marketplace_store_facilities')->where('id', $facilityId)
                ->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
            abort_unless($facility && $facility->is_active, 404);
            if ($enabled) {
                $accountEnabled = DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)
                    ->value('shared_pool_takeover_enabled');
                if ($store->status !== 'connected' || ! $accountEnabled) {
                    throw ValidationException::withMessages(['store' => 'Enable takeover for this connected Lazada store first.']);
                }
            }
            if ((bool) $facility->shared_pool_takeover_enabled !== $enabled) {
                DB::table('marketplace_store_facilities')->where('id', $facilityId)->update([
                    'shared_pool_takeover_enabled' => $enabled, 'updated_at' => now(),
                ]);
            }

            return ['store_id' => $storeId, 'facility_id' => $facilityId,
                'shared_pool_takeover_enabled' => $enabled];
        }, 3);
    }
}
