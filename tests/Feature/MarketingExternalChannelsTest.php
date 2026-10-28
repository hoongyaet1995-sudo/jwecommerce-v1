<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Crypt};
use App\Models\{User,MarketingChannelConnection};
use App\Services\Social\SocialHubOAuth;
class MarketingExternalChannelsTest extends TestCase {
 use RefreshDatabase;
 private function setupOwner():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  config(['services.tiktok.client_id'=>'tk-app','services.youtube.client_id'=>'yt-app']);
  Http::preventStrayRequests();
  $h=$this->mock(SocialHubOAuth::class);$h->shouldReceive('fingerprint')->andReturn('fixture');$h->shouldReceive('installation')->andReturn((object)['registered_domain'=>'127.0.0.1:8500']);
 }
 public function test_external_platform_authorize_uses_hub_without_meta_configuration():void {
  $this->setupOwner();$h=app(SocialHubOAuth::class);$h->shouldReceive('start')->once()->with(\Mockery::type('string'),'youtube')->andReturn(['transaction_id'=>(string)\Illuminate\Support\Str::uuid(),'launch_url'=>'https://social.jwaistudio.online/oauth/youtube/launch?ticket='.str_repeat('a',64)]);
  $this->post('/admin/marketing/channels/youtube/authorize')->assertRedirect();$this->assertNotNull(session('youtube_oauth'));Http::assertNothingSent();
 }
 public function test_refresh_token_is_encrypted_hidden_and_disconnect_clears_it():void {
  $this->setupOwner();$a=MarketingChannelConnection::create(['merchant_key'=>'jw-ecommerce','platform'=>'youtube','provider_account_id'=>'UC-test','app_id'=>'yt-app','name'=>'Channel','access_token'=>'access','refresh_token'=>'PRIVATE-REFRESH','permissions'=>['https://www.googleapis.com/auth/youtube.readonly'],'authorized_by'=>auth()->id(),'state'=>'connected']);
  $this->assertNotSame('PRIVATE-REFRESH',$a->getRawOriginal('refresh_token'));$this->assertArrayNotHasKey('refresh_token',$a->toArray());
  $this->postJson('/admin/marketing/channels/youtube/'.$a->id.'/disconnect')->assertOk()->assertDontSee('PRIVATE-REFRESH');$this->assertNull($a->fresh()->refresh_token);Http::assertNothingSent();
 }

 private function pending(string $p,string $id='UC-one'):array {
  return [$p.'_marketing_oauth'=>['actor'=>auth()->id(),'platform'=>$p,'expires'=>now()->addMinutes(5)->timestamp],$p.'_pending'=>Crypt::encryptString(json_encode(['actor'=>auth()->id(),'merchant'=>'jw-ecommerce','app'=>$p==='youtube'?'yt-app':'tk-app','installation'=>'fixture','transaction'=>(string)\Illuminate\Support\Str::uuid(),'expires'=>now()->addMinutes(5)->timestamp,'pages'=>[['id'=>$id,'name'=>'One']]]))];
 }
 public function test_selection_reconnect_preserves_refresh_and_other_accounts_and_is_one_time():void {
  $this->setupOwner();$h=app(SocialHubOAuth::class);$grant=['permissions'=>['https://www.googleapis.com/auth/youtube.readonly'],'pages'=>[['id'=>'UC-one','name'=>'One','access_token'=>'access','refresh_token'=>'PRIVATE-refresh','expires_at'=>now()->addHour()->toIso8601String()]]];
  $h->shouldReceive('call')->twice()->with('select',\Mockery::any(),'youtube')->andReturn($grant,array_replace_recursive($grant,['pages'=>[0=>['refresh_token'=>null,'access_token'=>'second']]]));
  $this->withSession($this->pending('youtube'))->postJson('/admin/marketing/channels/youtube/select',['account_id'=>'UC-other'])->assertUnprocessable();
  $this->postJson('/admin/marketing/channels/youtube/select',['account_id'=>'UC-one'])->assertOk()->assertDontSee('PRIVATE-refresh');$a=MarketingChannelConnection::firstOrFail();$id=$a->id;
  $other=MarketingChannelConnection::create(['merchant_key'=>'jw-ecommerce','platform'=>'youtube','provider_account_id'=>'UC-other','app_id'=>'yt-app','name'=>'Other','access_token'=>'other','refresh_token'=>'other-refresh','permissions'=>[],'state'=>'connected','authorized_by'=>auth()->id()]);$raw=$other->getRawOriginal('access_token');
  $this->withSession($this->pending('youtube'))->postJson('/admin/marketing/channels/youtube/select',['account_id'=>'UC-one'])->assertOk();$this->assertSame($id,$a->fresh()->id);$this->assertSame('PRIVATE-refresh',$a->fresh()->refresh_token);$this->assertSame($raw,$other->fresh()->getRawOriginal('access_token'));$this->postJson('/admin/marketing/channels/youtube/select',['account_id'=>'UC-one'])->assertStatus(409);Http::assertNothingSent();
 }
 public function test_handoff_actor_domain_state_and_replay_are_bound():void {
  $this->setupOwner();$h=app(SocialHubOAuth::class);$tx=(string)\Illuminate\Support\Str::uuid();$state=['state'=>str_repeat('s',64),'actor'=>auth()->id(),'merchant'=>'jw-ecommerce','app'=>'yt-app','installation'=>'fixture','transaction'=>$tx,'expires'=>now()->addMinute()->timestamp];
  $h->shouldReceive('call')->once()->with('redeem',\Mockery::any(),'youtube')->andReturn(['result'=>'select','pages'=>[['id'=>'UC-one','name'=>'One']]]);
  $url='/admin/social/youtube/hub-return?'.http_build_query(['state'=>str_repeat('s',64),'transaction'=>$tx,'handoff'=>str_repeat('a',64)]);
  $intent=['actor'=>auth()->id(),'platform'=>'youtube','expires'=>now()->addMinute()->timestamp];
  $this->withSession(['youtube_oauth'=>$state,'youtube_marketing_oauth'=>$intent])->get($url)->assertRedirect('/admin/marketing/channels/youtube?result=select');$this->assertNotNull(session('youtube_pending'));
  $this->get($url)->assertRedirect('/admin/marketing/channels/youtube?result=invalid_state');$this->assertNull(session('youtube_pending'));
  $state['actor']++;$this->withSession(['youtube_oauth'=>$state,'youtube_marketing_oauth'=>$intent])->get($url)->assertRedirect('/admin/marketing/channels/youtube?result=invalid_state');
  $state['actor']=auth()->id();$this->withSession(['youtube_oauth'=>$state,'youtube_marketing_oauth'=>$intent])->get('https://wrong.example'.$url)->assertRedirect('/admin/marketing/channels/youtube?result=invalid_state');Http::assertNothingSent();
 }
 public function test_rotated_tokens_are_saved_on_probe_failure_without_exposing_them():void {
  $this->setupOwner();$a=MarketingChannelConnection::create(['merchant_key'=>'jw-ecommerce','platform'=>'youtube','provider_account_id'=>'UC-one','app_id'=>'yt-app','name'=>'One','access_token'=>'old','refresh_token'=>'old-refresh','permissions'=>['https://www.googleapis.com/auth/youtube.readonly'],'state'=>'connected','authorized_by'=>auth()->id(),'expires_at'=>now()->subMinute()]);
  app(SocialHubOAuth::class)->shouldReceive('call')->once()->with('check',\Mockery::any(),'youtube')->andReturn(['result'=>'provider_unavailable','grant'=>['access_token'=>'PRIVATE-new','refresh_token'=>'PRIVATE-rotated','expires_at'=>now()->addHour()->toIso8601String()]]);
  $this->postJson('/admin/marketing/channels/youtube/'.$a->id.'/check')->assertOk()->assertJsonPath('accounts.0.state','unknown')->assertDontSee('PRIVATE');$this->assertSame('PRIVATE-rotated',$a->fresh()->refresh_token);$this->assertSame('PRIVATE-new',$a->fresh()->access_token);Http::assertNothingSent();
 }
 public function test_employees_cannot_authorize_check_or_disconnect_and_gets_never_probe():void {
  $this->setupOwner();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($u);app(SocialHubOAuth::class)->shouldNotReceive('call');app(SocialHubOAuth::class)->shouldNotReceive('start');
  foreach(['tiktok','youtube'] as $p){$this->get('/admin/marketing/channels/'.$p)->assertOk();$this->postJson('/admin/marketing/channels/'.$p.'/authorize')->assertForbidden();$this->postJson('/admin/marketing/channels/'.$p.'/'.\Illuminate\Support\Str::uuid().'/check')->assertForbidden();}Http::assertNothingSent();
 }
}
