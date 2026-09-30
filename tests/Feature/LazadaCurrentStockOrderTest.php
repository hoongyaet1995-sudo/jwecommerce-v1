<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\CurrentStockLedger;
use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LazadaCurrentStockOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazada_order_uses_exact_facility_and_cancellation_restores_once(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $store = DB::table('marketplace_stores')->insertGetId(['platform' => 'lazada',
            'shop_name' => 'Lazada test', 'region' => 'MY', 'environment' => 'sandbox', 'status' => 'connected']);
        $product = Product::create(['title' => 'Lazada Current Stock', 'handle' => 'lz-current', 'base_price' => 100]);
        $variant = $product->variants()->create(['sku' => 'LZ-CURRENT', 'title' => 'Test', 'price' => 100, 'stock' => 0]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store,
            'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id' => $store,
            'listing_variant_id' => $listing, 'product_variant_id' => $variant->id]);
        $warehouse = DB::table('inventory_warehouses')->insertGetId(['code' => 'LZ-CUR-WH',
            'name' => 'Lazada Current', 'status' => 'active', 'is_active' => true]);
        DB::table('marketplace_store_facilities')->insert(['marketplace_store_id' => $store,
            'provider_facility_id' => 'WH-1', 'name' => 'WH-1',
            'inventory_warehouse_id' => $warehouse, 'is_active' => true,
            'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'facility:'.$warehouse,
            'kind' => 'facility', 'warehouse_id' => $warehouse]);
        $balance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $pool,
            'product_variant_id' => $variant->id, 'version' => 0]);
        app(CurrentStockLedger::class)->setAbsolute($balance, 5, 'lz:test:count', 'First Count');
        $snapshot = app(MarketplaceOrderSnapshotService::class);
        $createdAt = now()->addMinute()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s O');
        $order = fn (string $status, string $updated) => [
            'order_id' => '123456', 'created_at' => $createdAt,
            'updated_at' => $updated, 'statuses' => [$status], 'currency' => 'MYR',
            'order_items' => [['order_item_id' => '987654', 'order_id' => '123456',
                'is_fbl' => 0, 'semi_managed' => false, 'warehouse_code' => 'WH-1',
                'status' => $status, 'product_id' => '100', 'sku_id' => '200']],
        ];
        $id = $snapshot->saveLazada($store, $order('unpaid', now()->addMinutes(2)->toIso8601String()));
        $this->assertSame(4, app(CurrentStockLedger::class)->quantity($balance));
        $snapshot->saveLazada($store, $order('shipped', now()->addMinutes(3)->toIso8601String()));
        $this->assertSame(4, app(CurrentStockLedger::class)->quantity($balance));
        $snapshot->saveLazada($store, $order('canceled', now()->addMinutes(4)->toIso8601String()));
        $this->assertSame(5, app(CurrentStockLedger::class)->quantity($balance));
        $this->assertDatabaseHas('marketplace_order_inventory', ['order_id' => $id, 'status' => 'released']);
    }
}
