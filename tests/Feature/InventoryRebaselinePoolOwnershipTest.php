<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryRebaselinePoolOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_warehouse_and_new_facility_can_reference_same_physical_warehouse(): void
    {
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'REBASE-PHYSICAL', 'name' => 'Physical warehouse', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_pools')->insert([
            'pool_key' => 'warehouse:'.$warehouse, 'kind' => 'warehouse',
            'warehouse_id' => $warehouse, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_pools')->insert([
            'pool_key' => 'facility:'.$warehouse, 'kind' => 'facility',
            'warehouse_id' => $warehouse, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('inventory_pools')->where('warehouse_id', $warehouse)->count());
    }
}
