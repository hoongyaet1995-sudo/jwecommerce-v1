<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class StoreEnrollmentService
{
    public function ensureWebsite(): object
    {
        DB::table('inventory_store_accounts')->insertOrIgnore([
            'account_key' => 'website',
            'marketplace_store_id' => null,
            'management_status' => 'managed',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('account_key', 'website')
            ->where('management_status', '!=', 'managed')
            ->update(['management_status' => 'managed', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);

        return DB::table('inventory_store_accounts')->where('account_key', 'website')->first();
    }

    public function enroll(int $storeId): object
    {
        DB::table('inventory_store_accounts')->insertOrIgnore([
            'account_key' => 'marketplace:'.$storeId,
            'marketplace_store_id' => $storeId,
            'management_status' => 'setup_required',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->first();
    }

    public function enrollConnectedStores(): void
    {
        DB::table('marketplace_stores')->where('status', 'connected')->whereIn('platform', ['shopee', 'lazada'])
            ->orderBy('id')->pluck('id')->each(fn ($id) => $this->enroll((int) $id));
    }
}
