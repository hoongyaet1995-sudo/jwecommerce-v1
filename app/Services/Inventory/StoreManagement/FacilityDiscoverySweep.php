<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class FacilityDiscoverySweep
{
    public function __construct(private readonly FacilityDiscovery $discovery) {}

    /** One provider read per run keeps the scheduled task bounded. */
    public function run(): array
    {
        $store = DB::table('marketplace_stores as store')
            ->leftJoin('marketplace_store_facility_scans as scan', 'scan.marketplace_store_id', '=', 'store.id')
            ->whereIn('store.platform', ['lazada', 'shopee'])
            ->where('store.status', 'connected')
            ->orderByRaw("COALESCE(scan.updated_at, '1970-01-01') asc")
            ->orderBy('store.id')
            ->first(['store.id']);
        if (! $store) {
            return ['stores' => 0, 'verified' => 0, 'pending' => 0];
        }

        $result = $this->discovery->refresh((int) $store->id);

        return ['stores' => 1, 'verified' => $result['status'] === 'verified' ? 1 : 0,
            'pending' => $result['status'] === 'verified' ? 0 : 1];
    }
}
