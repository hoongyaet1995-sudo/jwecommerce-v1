<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use App\Models\User;
class MarketingWhatsAppChannelsTest extends TestCase {
 use RefreshDatabase;
 public function test_whatsapp_entry_is_local_and_fails_closed_without_configuration():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  Http::preventStrayRequests();Queue::fake();
  $this->get('/admin/marketing/channels/whatsapp')->assertOk()->assertSee('channels.js')->assertSee('whatsapp');
  $this->postJson('/admin/marketing/channels/whatsapp/authorize')->assertStatus(409);
  Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_employee_cannot_start_whatsapp_authorization():void {
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($u);
  $this->postJson('/admin/marketing/channels/whatsapp/authorize')->assertForbidden();
 }

 public function test_signed_fake_handoff_saves_number_once_without_activating_messages():void {
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);Http::preventStrayRequests();Queue::fake();
  config(['app.url'=>'http://127.0.0.1','services.whatsapp.oauth_routing_ready'=>true,'services.whatsapp.app_id'=>'123','services.whatsapp.app_secret'=>'secret','services.whatsapp.embedded_signup_config_id'=>'456']);
  $tx=(string)\Illuminate\Support\Str::uuid();$h=$this->mock(\App\Services\Social\SocialHubOAuth::class);$h->shouldReceive('fingerprint')->andReturn(str_repeat('f',64));$h->shouldReceive('installation')->andReturn((object)['registered_domain'=>'127.0.0.1']);$h->shouldReceive('permitted')->andReturn(['oauthPermitted'=>true,'oauthReason'=>null]);
  $h->shouldReceive('start')->once()->with(\Mockery::type('string'),'whatsapp')->andReturn(['transaction_id'=>$tx,'launch_url'=>'https://social.jwaistudio.online/oauth/whatsapp/launch?ticket='.str_repeat('a',64)]);
  $h->shouldReceive('call')->once()->with('redeem',\Mockery::any(),'whatsapp')->andReturn(['result'=>'select','pages'=>[['id'=>'456','name'=>'My store']]]);
  $h->shouldReceive('call')->once()->with('select',\Mockery::any(),'whatsapp')->andReturn(['app_id'=>'123','pages'=>[['id'=>'456','waba_id'=>'789','access_token'=>'PRIVATE-WA-TOKEN']]]);
  Http::fake(function($r){return Http::response(str_contains($r->url(),'debug_token')?['data'=>['is_valid'=>true,'app_id'=>'123','scopes'=>['whatsapp_business_management','whatsapp_business_messaging']]]:['data'=>[['id'=>'456','verified_name'=>'My store','display_phone_number'=>'+60 123']]]);});
  $this->post('/admin/marketing/channels/whatsapp/authorize')->assertRedirect();$state=session('whatsapp_oauth');
  $this->get('/admin/social/whatsapp/hub-return?'.http_build_query(['state'=>$state['state'],'transaction'=>$tx,'handoff'=>str_repeat('b',64)]))->assertRedirect('/admin/marketing/channels/whatsapp?result=select');
  $this->postJson('/admin/marketing/channels/whatsapp/select',['account_id'=>'456'])->assertOk()->assertDontSee('PRIVATE-WA-TOKEN');
  $a=\App\Models\ManagedSocialAccount::where('platform','whatsapp')->firstOrFail();$this->assertFalse($a->inbound_enabled);$this->assertNotSame('PRIVATE-WA-TOKEN',$a->getRawOriginal('access_token'));
  $this->postJson('/admin/marketing/channels/whatsapp/select',['account_id'=>'456'])->assertStatus(409);$this->assertDatabaseCount('managed_social_accounts',1);Http::assertSentCount(2);Queue::assertNothingPushed();
 }
}
