<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Services\Marketplace\MarketplaceReconciliationRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceReconciliationRevisionTest extends TestCase
{
    use RefreshDatabase;
    public function test_confirm_guard_rejects_stale_reviews_and_rolls_back_failed_work(): void
    {
        $s=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','status'=>'connected','region'=>'MY','environment'=>'sandbox','shop_name'=>'Test']);
        $service=app(MarketplaceReconciliationRevision::class);
        $revision=$service->read($s);
        DB::table('marketplace_stores')->where('id',$s)->update(['status'=>'disconnected']);
        $called=false;
        try {
            $service->withCurrent($s,$revision,function () use (&$called) { $called=true; });
            $this->fail('Stale review accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('revision',$e->errors());
        }
        $this->assertFalse($called);
        $current=$service->read($s);
        try {
            $service->withCurrent($s,$current,function () use ($s) {
                DB::table('marketplace_stores')->where('id',$s)->update(['shop_name'=>'Should roll back']);
                throw new \RuntimeException('Deliberate failure');
            });
        } catch (\RuntimeException $e) { $this->assertSame('Deliberate failure',$e->getMessage()); }
        $this->assertSame('Test',DB::table('marketplace_stores')->where('id',$s)->value('shop_name'));
        $this->assertSame('saved',$service->withCurrent($s,$current,fn ()=>'saved'));
    }
    public function test_revision_changes_for_shared_stock_and_mapping_removal(): void
    {
        $s=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','status'=>'connected','region'=>'MY','environment'=>'sandbox','shop_name'=>'Test']);
        $p=Product::create(['title'=>'Test','handle'=>'test','base_price'=>10,'status'=>'draft']);
        $v=$p->variants()->create(['sku'=>'TEST','title'=>'One','price'=>10,'stock'=>10]);
        $l=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$s,'external_product_id'=>'1','external_variant_id'=>'0','observed_data'=>'{}','fetched_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$s,'listing_variant_id'=>$l,'product_variant_id'=>$v->id]);
        $service=app(MarketplaceReconciliationRevision::class);
        $first=$service->read($s);
        $this->assertSame($first,$service->read($s));
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'REV','name'=>'Review warehouse','status'=>'active','is_active'=>true]);
        DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$s,'warehouse_id'=>$warehouse,'platform_location_id'=>'WH1','is_enabled'=>true]);
        $routed=$service->read($s);
        $this->assertNotSame($first,$routed,'Routing changes invalidate the reviewed destination.');
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($v->id,'warehouse',$warehouse)['balance_id'];
        $beforeCount=$service->read($s);
        DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>10,'safety_stock'=>2,'counted_at'=>now()]);
        $this->assertNotSame($beforeCount,$service->read($s),'Physical counts invalidate review even without legacy stock changes.');
        app(\App\Services\Inventory\InventoryMutationService::class)->change($v->id,-1,'test:sale','Sale');
        $second=$service->read($s);
        $this->assertNotSame($first,$second);
        $order=app(\App\Services\Marketplace\MarketplaceOrderSnapshotService::class)->saveShopee($s,
            ['order_sn'=>'TEST','order_status'=>'READY_TO_SHIP','update_time'=>10]);
        $allocation=app(\App\Services\Inventory\StockAllocationService::class)->reserve('marketplace_order',$order,'1:0',$v->id,2);
        $reserved=$service->read($s);
        app(\App\Services\Inventory\StockAllocationService::class)->consume($allocation);
        $this->assertNotSame($reserved,$service->read($s), 'Consumption changes reconciliation even when available stock is unchanged.');
        $beforeIdentity=$service->read($s);
        DB::table('marketplace_stores')->where('id',$s)->update(['external_shop_id'=>'123']);
        $this->assertNotSame($beforeIdentity,$service->read($s));
        DB::table('marketplace_variant_mappings')->where('store_id',$s)->delete();
        $this->assertNotSame($second,$service->read($s));
    }
}
