<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class MarketplacePackingEndpointTest extends TestCase
{
 use RefreshDatabase;
 public function test_task_status_is_order_scoped_and_hides_request_payload():void
 {
  config(['marketplace.read_operations_enabled'=>true]);
  $actor=User::factory()->create(['is_admin'=>true]);
  $store=\Illuminate\Support\Facades\DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  $order=\Illuminate\Support\Facades\DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $id=app(\App\Services\Marketplace\MarketplaceRtsAttempts::class)->stage($order,$actor->id,'c3f9cd44-0f94-40d7-a2d3-d2bcc534bc87',['PK1']);
  $url='/admin/api/marketplace/orders/'.$order.'/fulfillment-attempts/'.$id;
  $this->getJson($url)->assertUnauthorized();
  $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonPath('status','queued')->assertJsonMissingPath('request')->assertJsonMissingPath('confirmed_result');
  $this->getJson('/admin/api/marketplace/orders/'.($order+1).'/fulfillment-attempts/'.$id)->assertNotFound();
  Http::preventStrayRequests();
  $this->postJson('/admin/api/marketplace/orders/'.($order+1).'/fulfillment-attempts/'.$id.'/reconcile')->assertNotFound();
  Http::assertNothingSent();
 }
 public function test_auth_gate_and_validation_prevent_provider_calls():void
 {
  Http::preventStrayRequests();
  config(['marketplace.read_operations_enabled'=>true,'marketplace.fulfillment_operations_enabled'=>false]);
  $url='/admin/api/marketplace/orders/1/pack';
  $this->postJson($url,[])->assertUnauthorized();
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->postJson($url,[])->assertUnauthorized();
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson($url,[])->assertStatus(503);
  config(['marketplace.fulfillment_operations_enabled'=>true]);
  $this->postJson($url,['requestKey'=>'invalid','packages'=>['PK1']])->assertUnprocessable();
  Http::assertNothingSent();
 }
}
