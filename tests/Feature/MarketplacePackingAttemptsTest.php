<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\Marketplace\MarketplacePackingAttempts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class MarketplacePackingAttemptsTest extends TestCase
{
 use RefreshDatabase;
 public function test_replay_keeps_one_attempt_and_new_key_cannot_overlap_pending_items():void
 {
  $actor=User::factory()->create(['is_admin'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $service=app(MarketplacePackingAttempts::class);
  $key='134eb60a-7f98-412c-8b4a-f036ba0e97de';
  $id=$service->stage($order,$actor->id,$key,['102','101'],'TFS');
  $this->assertSame($id,$service->stage($order,$actor->id,$key,['101','102'],'TFS'));
  $this->assertDatabaseCount('marketplace_fulfillment_attempts',1);
  $other=$service->stage($order,$actor->id,'a3b0d4e1-e30f-480a-a6ec-8cd8ae1c0f44',['103'],'TFS');
  $this->assertNotSame($id,$other);
  $this->expectException(\DomainException::class);
  $service->stage($order,$actor->id,'a025e14c-0cd7-4531-9120-719a32ee465a',['102'],'TFS');
 }
}
