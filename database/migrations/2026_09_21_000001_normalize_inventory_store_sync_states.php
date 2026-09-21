<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_store_accounts')->insertOrIgnore([
            'account_key' => 'website', 'marketplace_store_id' => null, 'management_status' => 'managed',
            'version' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('account_key', 'website')->update([
            'management_status' => 'managed', 'updated_at' => now(),
        ]);
        DB::table('marketplace_stores')->where('status', 'connected')->whereIn('platform', ['shopee', 'lazada'])
            ->orderBy('id')->get()->each(function ($store): void {
                DB::table('inventory_store_accounts')->insertOrIgnore([
                    'account_key' => 'marketplace:'.$store->id, 'marketplace_store_id' => $store->id,
                    'management_status' => 'setup_required', 'version' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // State normalization is intentionally irreversible; deleting enrolled accounts would destroy ownership history.
    }
};
