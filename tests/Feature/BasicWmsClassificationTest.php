<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Services\Inventory\TestDataClassificationService;
use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BasicWmsClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_store_provenance_reaches_existing_and_new_orders_without_reserving_stock():void
    {
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.automatic_orders_enabled'=>false]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $classify=app(TestDataClassificationService::class);
        foreach(['shopee','lazada'] as $platform){
            $store=DB::table('marketplace_stores')->insertGetId(['platform'=>$platform,'shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
            $save=fn($id)=>$platform==='shopee'
                ?app(MarketplaceOrderSnapshotService::class)->saveShopee($store,['order_sn'=>$id,'order_status'=>'READY_TO_SHIP','update_time'=>1234])
                :app(MarketplaceOrderSnapshotService::class)->saveLazada($store,['order_id'=>$id,'updated_at'=>'2026-09-08T12:00:00Z','statuses'=>['pending']]);
            $before=$save('111');
            $this->assertDatabaseHas('marketplace_orders',['id'=>$before,'data_classification'=>'unclassified']);
            $receipt=$classify->markTest('store',$store,$actor,'Official sandbox acceptance');
            $this->assertSame($receipt,$classify->markTest('store',$store,$actor,'Retry must retain original provenance'));
            $after=$save('222');
            foreach([$before,$after] as $id)$this->assertDatabaseHas('marketplace_orders',['id'=>$id,'data_classification'=>'test','classified_by'=>$actor->id]);
        }
        $this->assertDatabaseCount('stock_allocations',0);
    }

    public function test_classification_preserves_stock_and_cannot_overwrite_operational_identity():void
    {
        config(['marketplace.basic_wms_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Test','handle'=>'test','base_price'=>1])->variants()->create(['sku'=>'TEST','title'=>'One','price'=>1,'stock'=>17]);
        app(TestDataClassificationService::class)->markTest('sku',$variant->id,$actor,'Test shelf count');
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock'=>17,'data_classification'=>'test']);
        DB::table('product_variants')->where('id',$variant->id)->update(['data_classification'=>'operational']);
        try {app(TestDataClassificationService::class)->markTest('sku',$variant->id,$actor,'Relabel');$this->fail('Operational identity changed');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
    }
}
