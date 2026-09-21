<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\LegacyUnmappedStoreBalanceRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class LegacyUnmappedStoreBalanceRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_zeros_only_unmapped_legacy_mode_split_balances_and_records_an_audit(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('shopee', 'PHANTOM');
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:marketplace:'.$store->id, 'kind' => 'store', 'marketplace_store_id' => $store->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balanceId = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $sku['variant']->id,
            'physical_stock' => 6, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $preview = app(LegacyUnmappedStoreBalanceRepair::class)->inspect();
        $result = app(LegacyUnmappedStoreBalanceRepair::class)->apply($actor);

        $this->assertSame([$balanceId], $preview['balance_ids']);
        $this->assertSame(1, $result['repaired']);
        $this->assertSame(6, $result['removed_phantom_units']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $balanceId, 'physical_stock' => 0, 'version' => 2]);
        $this->assertDatabaseHas('inventory_reconciliations', [
            'inventory_balance_id' => $balanceId,
            'actor_id' => $actor->id,
            'reason' => 'Repair legacy mode split for unmapped marketplace store',
        ]);
        $this->assertSame(0, app(LegacyUnmappedStoreBalanceRepair::class)->inspect()['candidates']);
    }

    public function test_it_preserves_mapped_or_operationally_used_balances(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $mappedSku = $fixture->sku();
        $usedSku = $fixture->sku();
        $store = $fixture->store('lazada', 'PRESERVE');
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:marketplace:'.$store->id, 'kind' => 'store', 'marketplace_store_id' => $store->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([$mappedSku['variant']->id, $usedSku['variant']->id] as $variantId) {
            DB::table('inventory_balances')->insert([
                'inventory_pool_id' => $poolId, 'product_variant_id' => $variantId,
                'physical_stock' => 4, 'reserved_stock' => 0, 'safety_stock' => 0,
                'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => 'ITEM', 'external_variant_id' => 'VARIANT',
            'observed_data' => json_encode(['sku' => 'MAPPED']), 'fetched_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'listing_variant_id' => $listingId, 'store_id' => $store->id,
            'product_variant_id' => $mappedSku['variant']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $usedBalanceId = DB::table('inventory_balances')->where('inventory_pool_id', $poolId)
            ->where('product_variant_id', $usedSku['variant']->id)->value('id');
        DB::table('inventory_reconciliations')->insert([
            'inventory_balance_id' => $usedBalanceId, 'actor_id' => User::factory()->create()->id,
            'operation_key' => 'existing-use', 'request_hash' => hash('sha256', 'existing-use'),
            'before' => json_encode(['physical' => 4]), 'after' => json_encode(['physical' => 4]),
            'reason' => 'Existing operation', 'created_at' => now(),
        ]);

        $this->assertSame(0, app(LegacyUnmappedStoreBalanceRepair::class)->inspect()['candidates']);
    }
}
