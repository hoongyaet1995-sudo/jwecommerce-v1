<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Crypt};
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\Social\SocialHubOAuth;
class MarketingChannelsTest extends TestCase {
 use RefreshDatabase;
 private function owner():User{$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);config(['services.facebook.client_id'=>'test-app','services.facebook.client_secret'=>'test-secret','services.facebook.redirect'=>'https://store.example/admin/social/facebook/callback']);Http::preventStrayRequests();Queue::fake();return $u;}
 private function hub():\Mockery\MockInterface{$m=$this->mock(SocialHubOAuth::class);$m->shouldReceive('fingerprint')->andReturn(str_repeat('f',64));$m->shouldReceive('installation')->andReturn((object)['registered_domain'=>'127.0.0.1:8500']);return $m;}
 private function pending(User $u,string $platform='facebook'):array{$tx=(string)Str::uuid();return [$platform.'_marketing_oauth'=>['actor'=>$u->id,'platform'=>$platform,'expires'=>now()->addMinutes(5)->timestamp],$platform.'_pending'=>Crypt::encryptString(json_encode(['actor'=>$u->id,'merchant'=>'jw-ecommerce','app'=>'test-app','transaction'=>$tx,'installation'=>str_repeat('f',64),'expires'=>now()->addMinutes(5)->timestamp,'pages'=>[['id'=>'123','name'=>'Authorized page']]]))];}
 public function test_channels_list_is_real_local_data_and_never_runs_provider_probes():void{$this->owner();$this->hub();$this->get('/admin/marketing/channels')->assertOk()->assertSee('channels.js')->assertSee('Account connections')->assertDontSee('Simulated connection');Http::assertNothingSent();Queue::assertNothingPushed();}
 public function test_marketing_uses_existing_signed_hub_start_and_return_not_direct_callback():void{
  $u=$this->owner();$h=$this->hub();$tx=(string)Str::uuid();$h->shouldReceive('start')->once()->with(\Mockery::type('string'),'facebook')->andReturn(['transaction_id'=>$tx,'launch_url'=>'https://social.jwaistudio.online/oauth/facebook/launch?ticket='.str_repeat('a',64)]);$h->shouldReceive('call')->once()->with('redeem',\Mockery::any(),'facebook')->andReturn(['result'=>'select','pages'=>[['id'=>'123','name'=>'Authorized page']]]);
  $this->post('/admin/marketing/channels/facebook/authorize')->assertRedirect();$state=session('facebook_oauth');$this->get('/admin/social/facebook/hub-return?'.http_build_query(['state'=>$state['state'],'transaction'=>$tx,'handoff'=>str_repeat('b',64)]))->assertRedirect('/admin/marketing/channels?platform=facebook&result=select');$this->get('/admin/marketing/channels')->assertOk()->assertSee('Authorized page');Http::assertNothingSent();
 }
 public function test_selection_is_encrypted_one_time_and_never_activates_messaging():void{
  $u=$this->owner();$h=$this->hub();$h->shouldReceive('call')->once()->with('select',\Mockery::any(),'facebook')->andReturn(['permissions'=>['pages_show_list','pages_read_engagement'],'page'=>['id'=>'123','name'=>'Authorized page','access_token'=>'PRIVATE-TEST-TOKEN','tasks'=>['CREATE_CONTENT']]]);
  $this->withSession($this->pending($u));$this->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'999'])->assertUnprocessable();$this->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'123'])->assertOk()->assertDontSee('PRIVATE-TEST-TOKEN');$r=\App\Models\MarketingChannelConnection::firstOrFail();$this->assertNotSame('PRIVATE-TEST-TOKEN',$r->getRawOriginal('access_token'));$this->assertSame('unverified',$r->state);$this->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'123'])->assertStatus(409);$this->assertDatabaseCount('marketing_channel_connections',1);Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_employee_can_view_but_cannot_connect_or_mutate_and_missing_scope_is_not_claimed():void{
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($u);$this->get('/admin/marketing/channels')->assertOk();$this->postJson('/admin/marketing/channels/facebook/authorize')->assertForbidden();$this->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'123'])->assertForbidden();$this->postJson('/admin/marketing/channels/facebook/'.Str::uuid().'/disconnect')->assertForbidden();
 }
 public function test_public_http_origin_cannot_start_marketing_authorization():void{
  $this->owner();$h=$this->mock(SocialHubOAuth::class);$h->shouldReceive('fingerprint')->andReturn(str_repeat('f',64));$h->shouldReceive('installation')->andReturn((object)['registered_domain'=>'dev-shop.example.test']);$h->shouldNotReceive('start');
  $this->postJson('http://dev-shop.example.test/admin/marketing/channels/facebook/authorize')->assertStatus(409);Http::assertNothingSent();
 }
 public function test_marketing_handoff_on_wrong_customer_host_is_rejected_before_redemption():void{
  $u=$this->owner();$h=$this->hub();$h->shouldNotReceive('call');$tx=(string)Str::uuid();
  $this->withSession(['facebook_marketing_oauth'=>['actor'=>$u->id,'platform'=>'facebook','expires'=>now()->addMinute()->timestamp],'facebook_oauth'=>['state'=>str_repeat('s',64),'actor'=>$u->id,'merchant'=>'jw-ecommerce','app'=>'test-app','installation'=>str_repeat('f',64),'transaction'=>$tx,'expires'=>now()->addMinute()->timestamp]])
   ->get('https://other-client.example.test/admin/social/facebook/hub-return?'.http_build_query(['state'=>str_repeat('s',64),'transaction'=>$tx,'handoff'=>str_repeat('b',64)]))->assertRedirect('/admin/marketing/channels?platform=facebook&result=invalid_state');
  $this->assertNull(session('facebook_pending'));Http::assertNothingSent();
 }
 private function connection(array $values=[]):\App\Models\MarketingChannelConnection{return \App\Models\MarketingChannelConnection::create(array_replace(['merchant_key'=>'jw-ecommerce','platform'=>'facebook','provider_account_id'=>'123','app_id'=>'test-app','name'=>'Saved page','access_token'=>'PRIVATE-TEST-TOKEN','permissions'=>['pages_show_list','pages_read_engagement'],'authorized_by'=>auth()->id(),'state'=>'unverified'],$values));}
 public function test_platform_details_are_read_only_and_keep_all_existing_connections():void{
  $this->owner();$this->hub();$this->connection();$this->connection(['provider_account_id'=>'456','name'=>'Second page']);
  foreach(['facebook','instagram','tiktok','youtube'] as $platform)$this->get('/admin/marketing/channels/'.$platform)->assertOk()->assertSee('channels.css')->assertSee('channelsPlatform');
  $this->get('/admin/marketing/channels/unknown')->assertNotFound();$this->assertDatabaseCount('marketing_channel_connections',2);Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_adding_and_reconnecting_one_account_preserves_the_other_accounts():void{
  $u=$this->owner();$other=$this->connection(['provider_account_id'=>'789','name'=>'Existing page']);$old=$other->getRawOriginal('access_token');$h=$this->hub();
  $h->shouldReceive('call')->twice()->with('select',\Mockery::any(),'facebook')->andReturn(['permissions'=>['pages_show_list','pages_read_engagement'],'page'=>['id'=>'123','name'=>'New page','access_token'=>'NEW-PRIVATE-TOKEN']]);
  $this->withSession($this->pending($u))->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'123'])->assertOk();$id=\App\Models\MarketingChannelConnection::where('provider_account_id','123')->value('id');
  $this->withSession($this->pending($u))->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'123'])->assertOk();
  $this->assertDatabaseCount('marketing_channel_connections',2);$this->assertSame($id,\App\Models\MarketingChannelConnection::where('provider_account_id','123')->value('id'));$this->assertSame($old,$other->fresh()->getRawOriginal('access_token'));Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_expired_or_wrong_actor_pending_is_rejected_before_hub_call():void{
  $u=$this->owner();$h=$this->hub();$h->shouldNotReceive('call');$session=$this->pending($u);$session['facebook_marketing_oauth']['expires']=now()->subMinute()->timestamp;$this->withSession($session)->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'123'])->assertStatus(409);$session=$this->pending($u);$session['facebook_marketing_oauth']['actor']=$u->id+1;$this->withSession($session)->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'123'])->assertStatus(409);$this->assertDatabaseCount('marketing_channel_connections',0);
 }
 public function test_callback_wrong_state_and_replay_never_redeem_a_second_grant():void{
  $u=$this->owner();$h=$this->hub();$h->shouldNotReceive('call');$tx=(string)Str::uuid();$state=['state'=>str_repeat('s',64),'actor'=>$u->id,'merchant'=>'jw-ecommerce','app'=>'test-app','installation'=>str_repeat('f',64),'transaction'=>$tx,'expires'=>now()->addMinute()->timestamp];$intent=['actor'=>$u->id,'platform'=>'facebook','expires'=>now()->addMinute()->timestamp];$this->withSession(['facebook_oauth'=>$state,'facebook_marketing_oauth'=>$intent])->get('/admin/social/facebook/hub-return?'.http_build_query(['state'=>'wrong','transaction'=>$tx,'handoff'=>str_repeat('b',64)]))->assertRedirect('/admin/marketing/channels?platform=facebook&result=invalid_state');$this->get('/admin/social/facebook/callback?code=not-used')->assertRedirect();Http::assertNothingSent();
 }
 public function test_instagram_selection_preserves_linked_page_and_reports_missing_capabilities():void{
  $u=$this->owner();$h=$this->hub();$h->shouldReceive('call')->once()->with('select',\Mockery::any(),'instagram')->andReturn(['permissions'=>['instagram_basic','pages_read_engagement'],'page'=>['id'=>'123','facebook_page_id'=>'456','name'=>'Professional account','access_token'=>'PRIVATE-IG-TOKEN']]);$this->withSession($this->pending($u,'instagram'))->postJson('/admin/marketing/channels/instagram/select',['account_id'=>'123'])->assertOk()->assertJsonPath('accounts.0.capabilities.publishing_granted',false)->assertJsonPath('accounts.0.capabilities.analytics_enabled',false)->assertDontSee('PRIVATE-IG-TOKEN');$this->assertDatabaseHas('marketing_channel_connections',['platform'=>'instagram','linked_page_id'=>'456']);Http::assertNothingSent();
 }
 public function test_explicit_identity_check_and_disconnect_are_read_only_at_meta_and_do_not_touch_other_connections():void{
  $this->owner();$this->hub();$a=$this->connection();$other=$this->connection(['provider_account_id'=>'789','name'=>'Other page']);Http::fake(['https://graph.facebook.com/*'=>Http::response(['id'=>'123','name'=>'Verified name'])]);$this->postJson('/admin/marketing/channels/facebook/'.$a->id.'/check')->assertOk()->assertJsonPath('accounts.1.state','connected');$this->assertSame('connected',$a->fresh()->state);Http::assertSentCount(1);Http::assertSent(fn($r)=>$r->method()==='GET'&&!isset($r['access_token']));$this->postJson('/admin/marketing/channels/facebook/'.$a->id.'/disconnect')->assertOk();$this->assertSame('',$a->fresh()->access_token);$this->assertSame('unverified',$other->fresh()->state);$this->postJson('/admin/marketing/channels/facebook/'.$a->id.'/check')->assertOk();Http::assertSentCount(1);
 }
 public function test_expired_meta_auth_is_sanitized_without_leaking_tokens_or_retries():void{
  $this->owner();$this->hub();$a=$this->connection();Http::fake(['https://graph.facebook.com/*'=>Http::response(['error'=>['code'=>190,'message'=>'PRIVATE-TEST-TOKEN provider diagnostic']],400)]);$this->postJson('/admin/marketing/channels/facebook/'.$a->id.'/check')->assertOk()->assertJsonPath('accounts.0.state','authorization_expired')->assertDontSee('PRIVATE-TEST-TOKEN');Http::assertSentCount(1);
 }
 public function test_connection_flow_is_available_with_renderer_disabled_but_wrong_domain_is_blocked():void{
  $this->owner();config(['marketing.execution_enabled'=>false]);$h=$this->mock(SocialHubOAuth::class);$h->shouldReceive('fingerprint')->andReturn(str_repeat('f',64));$h->shouldReceive('installation')->andReturn((object)['registered_domain'=>'other.example']);$h->shouldNotReceive('start');$this->postJson('/admin/marketing/channels/facebook/authorize')->assertStatus(409);$this->assertDatabaseCount('marketing_channel_connections',0);
 }

}
