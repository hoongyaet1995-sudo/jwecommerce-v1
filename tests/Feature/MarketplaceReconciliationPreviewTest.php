<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceReconciliationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_demand_mapping_uses_the_selected_store_only(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','status'=>'connected','region'=>'MY','environment'=>'sandbox','shop_name'=>'Test']);
        $other=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','status'=>'connected','region'=>'MY','environment'=>'sandbox','shop_name'=>'Other']);
        $product=\App\Models\Product::create(['title'=>'Test','handle'=>'test','base_price'=>10,'status'=>'draft']);
        $variant=$product->variants()->create(['sku'=>'JW-1','title'=>'One','price'=>10,'stock'=>7,'enabled'=>true]);
        foreach ([$store,$other] as $sid) {
            $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$sid,'external_product_id'=>'1','external_variant_id'=>'0','observed_data'=>'{}','fetched_at'=>now()]);
            if ($sid===$other) DB::table('marketplace_variant_mappings')->insert(['store_id'=>$sid,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
        }
        app(MarketplaceOrderSnapshotService::class)->saveShopee($store,['order_sn'=>'ONE','order_status'=>'READY_TO_SHIP','update_time'=>10,'fulfillment_flag'=>'fulfilled_by_local_seller','item_list'=>[['item_id'=>1,'model_id'=>0,'model_quantity_purchased'=>2]]]);
        $url='/admin/api/marketplace/inventory/reconciliation?storeId='.$store;
        $this->getJson($url)->assertOk()->assertJsonPath('orders.data.0.demand.lines.0.mapping_status','unmapped');
        $listing=DB::table('marketplace_listing_variants')->where('store_id',$store)->value('id');
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('orders.data.0.demand.lines.0.product_variant_id',$variant->id)
            ->assertJsonPath('orders.data.0.demand.lines.0.mapping_status','mapped')
            ->assertJsonPath('orders.data.0.demand.lines.0.available_stock',7)
            ->assertJsonPath('orders.data.0.demand.lines.0.stock_version',0);
        $this->assertSame(7,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('stock_allocations',0);
        app(\App\Services\Inventory\InventoryMutationService::class)->change($variant->id,-1,'test:concurrent','Concurrent demand');
        $this->getJson($url)->assertOk()->assertJsonPath('orders.data.0.demand.lines.0.available_stock',6)
            ->assertJsonPath('orders.data.0.demand.lines.0.stock_version',1);
        DB::table('marketplace_variant_mappings')->where('store_id',$store)->delete();
        $this->getJson($url)->assertOk()->assertJsonPath('orders.data.0.demand.lines.0.mapping_status','unmapped')
            ->assertJsonPath('orders.data.0.demand.lines.0.available_stock',null);
    }

    public function test_changed_snapshot_requires_reload_instead_of_combining_versions(): void
    {
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','status'=>'connected','region'=>'MY','environment'=>'sandbox','shop_name'=>'Test']);
        $snapshots=app(MarketplaceOrderSnapshotService::class);
        $payload=['order_sn'=>'RACE','order_status'=>'READY_TO_SHIP','update_time'=>10,'fulfillment_flag'=>'fulfilled_by_local_seller',
            'item_list'=>[['item_id'=>1,'model_id'=>0,'model_quantity_purchased'=>2]]];
        $snapshots->saveShopee($store,$payload);
        $page=DB::table('marketplace_orders')->select('id','store_id','platform_status','platform_updated_at')->paginate();
        $payload['update_time']=20;
        $payload['order_status']='SHIPPED';
        $snapshots->saveShopee($store,$payload);
        app(\App\Services\Marketplace\MarketplaceReconciliationPage::class)->annotate($page);
        $this->assertSame('snapshot_changed',$page->items()[0]->demand['error']);
        $this->assertSame([],$page->items()[0]->demand['lines']);
    }

    public function test_preview_preserves_old_unpaid_and_unknown_states_without_exposing_payloads(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','status'=>'connected','region'=>'MY','environment'=>'sandbox','shop_name'=>'Test']);
        foreach (['UNPAID','READY_TO_SHIP','FUTURE_STATUS','COMPLETED','CANCELLED'] as $status) {
            app(MarketplaceOrderSnapshotService::class)->saveShopee($store, ['order_sn'=>$status,'order_status'=>$status,'update_time'=>10,'create_time'=>1,'recipient_address'=>['name'=>'PRIVATE RECIPIENT']]);
        }
        $other = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','status'=>'connected','region'=>'MY','environment'=>'sandbox','shop_name'=>'Other']);
        app(MarketplaceOrderSnapshotService::class)->saveShopee($other, ['order_sn'=>'OTHER-STORE','order_status'=>'READY_TO_SHIP','update_time'=>20]);
        $url='/admin/api/marketplace/inventory/reconciliation?storeId='.$store.'&per_page=2';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]));
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $response=$this->getJson($url)->assertOk()->assertJsonPath('orders.total',3)
            ->assertJsonPath('orders.data.0.platform_status','UNPAID')
            ->assertJsonPath('orders.data.0.demand.error','unsupported_fulfillment')
            ->assertJsonPath('activation_ready',false)->assertJsonPath('coverage_verified',false);
        $this->assertStringNotContainsString('PRIVATE RECIPIENT',$response->getContent());
        $this->assertStringNotContainsString('encrypted_payload',$response->getContent());
        $this->assertStringNotContainsString('OTHER-STORE',$response->getContent());
        $this->getJson($url.'&page=2')->assertOk()->assertJsonPath('orders.data.0.platform_status','FUTURE_STATUS');
        $this->assertDatabaseCount('stock_allocations',0);
        $this->assertDatabaseCount('marketplace_inventory_policies',0);
        $this->getJson('/admin/api/marketplace/inventory/reconciliation')->assertUnprocessable();
        $this->getJson('/admin/api/marketplace/inventory/reconciliation?storeId=999')->assertNotFound();
        $this->getJson($url.'&per_page=101')->assertUnprocessable();
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503)->assertJsonPath('code','marketplace_operations_disabled');
    }
}
