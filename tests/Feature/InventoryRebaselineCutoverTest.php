<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\RebaselineCutover;
use App\Services\Inventory\StoreManagement\RebaselineFreeze;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class InventoryRebaselineCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.rebaseline_cutover_enabled' => true]);
    }

    public function test_cutover_archives_legacy_stock_without_copying_it_into_the_new_facility_owner(): void
    {
        [$admin, $variant, $warehouse, $oldBalance] = $this->websiteWithLegacyStock();
        $epoch = app(RebaselineFreeze::class)->start($admin, 'cutover-freeze-1');
        $review = app(RebaselineCutover::class)->review($admin, $epoch['epoch_id']);
        $receipt = app(RebaselineCutover::class)->confirm($admin, $epoch['epoch_id'], $review['hash'], 'cutover-confirm-1');

        $this->assertSame($epoch['epoch_id'], $receipt['active_epoch_id']);
        $this->assertSame('counting', $receipt['status']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'physical_stock' => 7]);
        $this->assertDatabaseHas('inventory_rebaseline_legacy_balances', [
            'epoch_id' => $epoch['epoch_id'], 'inventory_balance_id' => $oldBalance, 'physical_stock' => 7,
        ]);
        $owner = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'facility')->where('pool.warehouse_id', $warehouse)
            ->where('balance.product_variant_id', $variant)->first(['balance.*']);
        $this->assertNotNull($owner);
        $this->assertNull($owner->physical_stock);
        $this->assertNull($owner->counted_at);
        $this->assertSame($epoch['epoch_id'], $owner->rebaseline_epoch_id);
        $this->assertSame($receipt, app(RebaselineCutover::class)->confirm($admin, $epoch['epoch_id'], $review['hash'], 'cutover-confirm-1'));
    }

    public function test_changed_legacy_balance_invalidates_review_without_archiving_or_activating(): void
    {
        [$admin, , , $oldBalance] = $this->websiteWithLegacyStock();
        $epoch = app(RebaselineFreeze::class)->start($admin, 'cutover-freeze-2');
        $review = app(RebaselineCutover::class)->review($admin, $epoch['epoch_id']);
        DB::table('inventory_balances')->where('id', $oldBalance)->update(['version' => 2]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(RebaselineCutover::class)->confirm($admin, $epoch['epoch_id'], $review['hash'], 'cutover-confirm-2');
        } finally {
            $this->assertDatabaseCount('inventory_rebaseline_legacy_balances', 0);
            $this->assertNull(DB::table('inventory_configuration')->where('id', 1)->value('active_rebaseline_epoch_id'));
        }
    }

    private function websiteWithLegacyStock(): array
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $variant = (new StoreInventoryFixture)->sku()['variant'];
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'REBASE-WEBSITE', 'name' => 'Website physical site', 'status' => 'active',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_fulfillment_routes')->insert([
            'channel_key' => 'website', 'warehouse_id' => $warehouse, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'warehouse:'.$warehouse, 'kind' => 'warehouse',
            'warehouse_id' => $warehouse, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $oldBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $pool, 'product_variant_id' => $variant->id,
            'physical_stock' => 7, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$admin, $variant->id, $warehouse, $oldBalance];
    }
}
