<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class LazadaTestFacilityAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.multiwarehouse_test_acceptance_enabled' => true]);
    }

    public function test_only_designated_test_seller_mapped_variant_and_bound_facility_are_eligible(): void
    {
        [$storeId, $variantId, $facilityId] = $this->context();
        $policy = app(LazadaTestFacilityAcceptance::class);
        $this->assertTrue(LazadaTestFacilityAcceptance::appliesToStore($storeId));
        $this->assertTrue($policy->inspect($storeId, $variantId, $facilityId)['eligible']);
        config(['marketplace.lazada.test_store_id' => $storeId + 100]);
        $this->assertContains('test_seller_not_approved', array_column(
            $policy->inspect($storeId, $variantId, $facilityId)['blockers'], 'code'));
        config(['marketplace.lazada.test_store_id' => $storeId]);

        $production = (new StoreInventoryFixture)->store('lazada', 'PRODUCTION');
        DB::table('marketplace_stores')->where('id', $production->id)->update([
            'account_mode' => 'production', 'environment' => 'live',
        ]);
        $this->assertFalse(LazadaTestFacilityAcceptance::appliesToStore((int) $production->id));
        $this->assertContains('test_seller_not_approved', array_column(
            $policy->inspect((int) $production->id, $variantId, $facilityId)['blockers'], 'code'));
        $this->assertContains('mapping_missing', array_column(
            $policy->inspect($storeId, $variantId + 999, $facilityId)['blockers'], 'code'));
        config(['marketplace.multiwarehouse_test_acceptance_enabled' => false]);
        $this->assertContains('test_acceptance_disabled', array_column(
            $policy->inspect($storeId, $variantId, $facilityId)['blockers'], 'code'));
    }

    public function test_counted_legacy_owner_blocks_test_first_count_without_changing_stock(): void
    {
        [$storeId, $variantId, $facilityId, $warehouseId] = $this->context();
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'warehouse:'.$warehouseId, 'kind' => 'warehouse',
            'warehouse_id' => $warehouseId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $variantId,
            'physical_stock' => 9, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertContains('legacy_stock_present', array_column(
            app(LazadaTestFacilityAcceptance::class)->inspect($storeId, $variantId, $facilityId)['blockers'], 'code'));
        $this->assertSame(9, (int) DB::table('inventory_balances')
            ->where('inventory_pool_id', $poolId)->value('physical_stock'));
    }

    public function test_counted_website_stock_does_not_block_test_store_facility_operations(): void
    {
        [$storeId, $variantId, $facilityId] = $this->context();
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:website', 'kind' => 'store',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $variantId,
            'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue(app(LazadaTestFacilityAcceptance::class)
            ->inspect($storeId, $variantId, $facilityId)['eligible']);
    }

    public function test_mapping_in_another_store_does_not_lock_the_test_store_sku(): void
    {
        [$storeId, $variantId, $facilityId] = $this->context();
        $other = (new StoreInventoryFixture)->store('shopee', 'OTHER-STORE');
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $other->id, 'external_product_id' => 'OTHER-100',
            'external_variant_id' => 'OTHER-200', 'observed_data' => '{}',
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $other->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variantId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue(app(LazadaTestFacilityAcceptance::class)
            ->inspect($storeId, $variantId, $facilityId)['eligible']);
    }

    public function test_admin_can_read_eligibility_but_guest_cannot(): void
    {
        [$storeId, $variantId, $facilityId] = $this->context();
        $url = "/admin/api/inventory/store-management/stores/{$storeId}/skus/{$variantId}/test-facility-acceptance";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))
            ->getJson($url)->assertOk()->assertJsonPath('eligible', true)
            ->assertJsonPath('facilities.0.id', $facilityId);
    }

    public function test_unrelated_open_order_does_not_block_but_matching_or_unknown_order_does(): void
    {
        [$storeId, $variantId, $facilityId] = $this->context();
        $policy = app(LazadaTestFacilityAcceptance::class);
        $orderId = DB::table('marketplace_orders')->insertGetId([
            'store_id' => $storeId, 'external_order_id' => 'OPEN-1',
            'platform_status' => 'pending', 'platform_updated_at' => 1,
            'encrypted_payload' => Crypt::encryptString(json_encode([
                'order_items' => [['product_id' => 901, 'sku_id' => 902]],
            ])), 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertTrue($policy->inspect($storeId, $variantId, $facilityId)['eligible']);

        DB::table('marketplace_orders')->where('id', $orderId)->update([
            'encrypted_payload' => Crypt::encryptString(json_encode([
                'order_items' => [['product_id' => 100, 'sku_id' => 200]],
            ])),
        ]);
        $this->assertContains('open_test_store_order', array_column(
            $policy->inspect($storeId, $variantId, $facilityId)['blockers'], 'code'));

        DB::table('marketplace_orders')->where('id', $orderId)->update(['encrypted_payload' => 'corrupt']);
        $this->assertContains('unreadable_test_store_order', array_column(
            $policy->inspect($storeId, $variantId, $facilityId)['blockers'], 'code'));
    }

    public function test_matching_open_order_already_excluded_as_historical_without_allocation_does_not_block_count(): void
    {
        [$storeId, $variantId, $facilityId] = $this->context();
        $orderId = DB::table('marketplace_orders')->insertGetId([
            'store_id' => $storeId, 'external_order_id' => 'HISTORICAL-1',
            'platform_status' => 'ready_to_ship', 'platform_updated_at' => 1,
            'encrypted_payload' => Crypt::encryptString(json_encode([
                'order_items' => [['product_id' => 100, 'sku_id' => 200, 'order_item_id' => 'ITEM-1']],
            ])), 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_inventory_items')->insert([
            'order_id' => $orderId, 'line_key' => 'ITEM-1',
            'external_product_id' => '100', 'external_variant_id' => '200',
            'warehouse_code' => 'WH-TEST', 'status' => 'skipped_historical',
            'stock_allocation_id' => null, 'platform_updated_at' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue(app(LazadaTestFacilityAcceptance::class)
            ->inspect($storeId, $variantId, $facilityId)['eligible']);

        DB::table('marketplace_inventory_items')->where('order_id', $orderId)
            ->update(['status' => 'reserved']);
        $this->assertContains('open_test_store_order', array_column(app(LazadaTestFacilityAcceptance::class)
            ->inspect($storeId, $variantId, $facilityId)['blockers'], 'code'));
    }

    private function context(): array
    {
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'ISOLATED-TEST');
        DB::table('marketplace_stores')->where('id', $store->id)->update([
            'account_mode' => 'test', 'environment' => 'live',
            'external_shop_id' => '300163725140', 'data_classification' => 'test',
        ]);
        config(['marketplace.lazada.test_store_id' => (string) $store->id]);
        $variant = $fixture->sku()['variant'];
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-TEST', 'name' => 'Test warehouse'],
        ]);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-TEST', 'name' => 'Test physical warehouse',
            'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $facilityId = (int) DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-TEST')->value('id');
        DB::table('marketplace_store_facilities')->where('id', $facilityId)
            ->update(['inventory_warehouse_id' => $warehouseId]);

        return [(int) $store->id, (int) $variant->id, $facilityId, $warehouseId];
    }
}
