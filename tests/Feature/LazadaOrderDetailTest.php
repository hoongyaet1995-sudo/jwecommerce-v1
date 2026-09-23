<?php
namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceOrderDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Models\MarketplaceStoreToken;
use Tests\TestCase;

class LazadaOrderDetailTest extends TestCase
{
    use RefreshDatabase;
    public function test_tracking_and_courier_come_from_lazada_order_items():void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        $payload=['order_items'=>[['order_item_id'=>'101','status'=>'ready_to_ship','package_id'=>'PK1',
            'product_main_image'=>'https://sg-test-11.slatic.net/p/image.jpg',
            'tracking_code'=>'649173598414','shipment_provider'=>'Drop-off: LEX MY, Delivery: J&T MY']]];
        $id=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'ready_to_ship','platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString(json_encode($payload)),'fetched_at'=>now()]);
        $detail=app(MarketplaceOrderDetailService::class)->read($id);
        $this->assertSame('649173598414',$detail['tracking_number']);
        $this->assertSame('Drop-off: LEX MY, Delivery: J&T MY',$detail['shipping_carrier']);
        $this->assertSame('https://sg-test-11.slatic.net/p/image.jpg',$detail['lines'][0]['image_url']);
        $response=$this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]))->getJson('/admin/api/marketplace/orders');
        $response->assertOk()->assertJsonPath('data.0.tracking_number','649173598414');
        $response->assertJsonPath('data.0.thumbnail_url','https://sg-test-11.slatic.net/p/image.jpg');
    }
    public function test_expired_official_test_access_does_not_advertise_downloadable_awb():void
    {
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Official Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>$store,'credential_source'=>'official_test','application_id'=>'456',
            'access_token'=>'fixture','access_expires_at'=>now()->addHour(),'test_lease_expires_at'=>now()->subMinute()]);
        $item=['order_item_id'=>'101','package_id'=>'PK1','status'=>'ready_to_ship','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0];
        $id=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'ready_to_ship',
            'platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString(json_encode(['order_items'=>[$item]])),'fetched_at'=>now()]);
        $detail=app(MarketplaceOrderDetailService::class)->read($id);
        $this->assertSame(['PK1'],$detail['label_packages']);
        $this->assertFalse($detail['capabilities']['airway_bill']);
    }
    public function test_current_official_test_lease_allows_awb_without_access_expiry():void
    {
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Official Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>$store,'credential_source'=>'official_test','application_id'=>'456',
            'access_token'=>'fixture','access_expires_at'=>null,'test_lease_expires_at'=>now()->addHour()]);
        $item=['order_item_id'=>'101','package_id'=>'PK1','status'=>'packed','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0];
        $id=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed',
            'platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString(json_encode(['order_items'=>[$item]])),'fetched_at'=>now()]);
        $detail=app(MarketplaceOrderDetailService::class)->read($id);
        $this->assertSame(['PK1'],$detail['label_packages']);
        $this->assertTrue($detail['capabilities']['airway_bill']);
    }
    public function test_rts_capability_excludes_claimed_packages():void
    {
        config(['marketplace.fulfillment_operations_enabled'=>true]);
        $actor=\App\Models\User::factory()->create(['is_admin'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture','access_expires_at'=>now()->addHour()]);
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

