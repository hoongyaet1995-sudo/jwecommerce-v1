<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\Marketplace\MarketplaceRtsAttempts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class MarketplaceRtsAttemptsTest extends TestCase
{
 use RefreshDatabase;
 public function test_replay_is_stable_and_overlapping_packages_are_blocked():void
 {
  $actor=User::factory()->create(['is_admin'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $service=app(MarketplaceRtsAttempts::class);$key='3e7f02db-4198-4fdc-a5eb-66078c8a7531';
  $id=$service->stage($order,$actor->id,$key,['B','A']);
  $this->assertSame($id,$service->stage($order,$actor->id,$key,['A','B']));
  $this->assertSame(1,DB::table('marketplace_fulfillment_attempts')->count());
  $this->expectException(\DomainException::class);
  $service->stage($order,$actor->id,'c4268a3e-5698-4721-b65f-37010e41e9aa',['A']);
 }
}
