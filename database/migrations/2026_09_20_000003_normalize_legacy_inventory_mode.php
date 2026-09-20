<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if (! $configuration || $configuration->operating_mode !== 'independent_warehouses') {
                return;
            }

            DB::table('inventory_pools')->insertOrIgnore([
                'pool_key' => 'shared',
                'kind' => 'shared',
                'warehouse_id' => null,
                'marketplace_store_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventory_configuration')->where('id', 1)->update([
                'operating_mode' => 'shared_pool',
                'version' => (int) $configuration->version + 1,
            ]);
        });
    }

    public function down(): void
    {
        // The legacy warehouse mode is intentionally not restored. Existing legacy
        // balances remain untouched for audit and no stock quantity is copied.
    }
};
