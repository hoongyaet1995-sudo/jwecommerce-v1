<?php
namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceOrderDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LazadaOrderDetailTest extends TestCase
{
    use RefreshDatabase;
    public function test_rts_capability_excludes_claimed_packages():void
    {
        config(['marketplace.fulfillment_operations_enabled'=>true]);
        $actor=\App\Models\User::factory()->create(['is_admin'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        $line=['order_item_id'=>'101','package_id'=>'PK1','status'=>'packed','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0,'semi_managed'=>false];
        $id=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed','platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString(json_encode(['order_items'=>[$line]])),'fetched_at'=>now()]);
        $service=app(MarketplaceOrderDetailService::class);
        $this->assertSame(['PK1'],$service->read($id)['rts_packages']);
        app(\App\Services\Marketplace\MarketplaceRtsAttempts::class)->stage($id,$actor->id,'8f8247c4-6685-4c3c-92c4-cbf65fa8b4ee',['PK1']);
        DB::table('marketplace_fulfillment_attempts')->where('order_id',$id)->update(['created_at'=>'2026-09-08 09:08:00']);
        $this->assertSame('2026-09-08T09:08:00+00:00',$service->read($id)['fulfillment_attempts'][0]->created_at);
        $this->assertSame([],$service->read($id)['rts_packages']);
        $this->assertFalse($service->read($id)['capabilities']['fulfillment']);
        $this->assertTrue($service->read($id)['capabilities']['airway_bill']);
    }
    public function test_lazada_items_remain_separate_and_exclude_unrequested_private_fields():void
    {
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        $line=['product_id'=>'200','sku_id'=>'300','name'=>'Shirt','variation'=>'Red M','sku'=>'SHIRT','paid_price'=>99,'status'=>'pending','digital_delivery_info'=>'private@example.com'];
        $payload=['order_items'=>[array_replace($line,['order_item_id'=>'101']),array_replace($line,['order_item_id'=>'102'])],
            'address_shipping'=>['first_name'=>'Test','last_name'=>'Recipient','phone'=>'12345','address1'=>'Test street',
                'address2'=>'Unit 1','city'=>'Cyberjaya','country'=>'Malaysia','post_code'=>'63000'],
            'address_billing'=>['address1'=>'Private billing address']];
        $id=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString(json_encode($payload)),'fetched_at'=>now()]);
        $result=app(MarketplaceOrderDetailService::class)->read($id);
        $this->assertCount(2,$result['lines']);
        $this->assertSame(['101','102'],array_column($result['lines'],'external_order_item_id'));
        $this->assertSame([1,1],array_column($result['lines'],'quantity'));
        $this->assertSame('Shirt',$result['lines'][0]['title']);
        $this->assertSame('300',$result['lines'][0]['external_variant_id']);
        $this->assertStringNotContainsString('private@example.com',json_encode($result));
        $this->assertFalse($result['capabilities']['fulfillment']);
        $this->assertArrayNotHasKey('recipient',$result);
        $this->assertStringNotContainsString('Test street',json_encode($result));
        $detail=app(MarketplaceOrderDetailService::class)->read($id,true);
        $this->assertSame('Test Recipient',$detail['recipient']['name']);
        $this->assertSame('Test street, Unit 1',$detail['recipient']['full_address']);
        $this->assertSame('63000',$detail['recipient']['zipcode']);
        $this->assertNull($detail['recipient']['state']);
        $this->assertStringNotContainsString('Private billing address',json_encode($detail));
    }
}

