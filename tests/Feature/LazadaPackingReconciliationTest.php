<?php
namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Models\User;
use App\Services\Marketplace\LazadaPackingReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LazadaPackingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_readback_requires_every_selected_item_and_never_resends(): void
    {
        config(['marketplace.read_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture','access_expires_at'=>now()->addHour()]);
        $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
        $id=DB::table('marketplace_fulfillment_attempts')->insertGetId(['order_id'=>$order,'actor_id'=>$actor->id,'request_key'=>'966e4fd7-509f-426f-9304-261b9e52e4b1','operation'=>'pack','status'=>'uncertain','item_ids'=>'["101","102"]','request'=>'{}','created_at'=>now(),'updated_at'=>now()]);
        $line=['order_id'=>'100','order_item_id'=>'101','status'=>'packed','package_id'=>'PK1'];
        Http::preventStrayRequests();
        Http::fake(['*/order/items/get*'=>Http::sequence()
            ->push(['code'=>'0','data'=>[$line]])
            ->push(['code'=>'0','data'=>[$line,array_merge($line,['order_item_id'=>'102','status'=>'pending'])]])
            ->push(['code'=>'0','data'=>[$line,array_merge($line,['order_item_id'=>'102','package_id'=>'PK2','status'=>'ready_to_ship'])]])]);
        $service=app(LazadaPackingReconciliation::class);
        $this->assertFalse($service->reconcile($id)['confirmed']);
        $this->assertFalse($service->reconcile($id)['confirmed']);
        $this->assertSame('uncertain',DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status'));
        $this->actingAs($actor)->postJson("/admin/api/marketplace/orders/{$order}/fulfillment-attempts/{$id}/reconcile")
            ->assertOk()->assertJsonPath('confirmed',true)->assertJsonPath('snapshot_refreshed',false);
        $saved=DB::table('marketplace_fulfillment_attempts')->find($id);
        $this->assertSame('succeeded',$saved->status);
        $this->assertSame('PK2',json_decode($saved->confirmed_result,true)['items']['102']['package_id']);
        Http::assertSentCount(3);
    }
}
