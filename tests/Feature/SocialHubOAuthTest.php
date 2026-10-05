<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Social\SocialProtocol;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialHubOAuthTest extends TestCase
{
    private string $transaction;
    public function test_pairing_binds_once_and_rejects_wrong_code_domain_expiry_and_replay(): void
    {
        DB::table('social_hub_settings')->delete();config(['app.url'=>'https://shop.example.com']);
        Http::fake(['*'=>Http::response([],503)]);
        $code=str_repeat('a',64);$client=(string)Str::uuid();$key=(string)Str::uuid();$secret=str_repeat('b',64);
        DB::table('social_pairing_tokens')->insert(['token_hash'=>hash('sha256',$code),'domain'=>'shop.example.com','expires_at'=>now()->addHour()]);
        $path='/api/social/hub/pair';$data=['client_id'=>$client,'key_id'=>$key,'secret'=>$secret,'registered_domain'=>'shop.example.com','hub_url'=>'https://social.jwaistudio.online'];
        $send=function($token,$domain='shop.example.com')use($data,$client,$key,$secret,$path){$d=array_replace($data,['registered_domain'=>$domain]);$body=json_encode($d);$headers=SocialProtocol::headers($client,$key,$secret,'hub-to-client','POST',$path,$body);return $this->call('POST',$path,[],[],[],array_merge(['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_SOCIAL_PAIRING_CODE'=>$token],collect($headers)->mapWithKeys(fn($v,$k)=>['HTTP_'.strtoupper(str_replace('-','_',$k))=>$v])->all()),$body);};
        $send(str_repeat('c',64))->assertForbidden();$send($code,'other.example.com')->assertForbidden();
        DB::table('social_pairing_tokens')->update(['expires_at'=>now()->subMinute()]);$send($code)->assertForbidden();
        DB::table('social_pairing_tokens')->update(['expires_at'=>now()->addHour()]);$send($code)->assertOk()->assertJsonPath('status','paired');
        $this->assertSame($secret,Crypt::decryptString(DB::table('social_hub_settings')->value('encrypted_secret')));
        $send($code)->assertForbidden();$this->assertSame($client,DB::table('social_hub_settings')->value('client_id'));
    }
    public function test_installation_is_developer_only_even_for_store_super_admins(): void
    {
        \Illuminate\Support\Facades\Schema::create('store_settings', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
        config(['services.social_hub.developer_user_ids'=>[]]);
        $this->get('/admin/social-hub/installation')->assertNotFound();
        $this->getJson('/admin/api/social-hub/installation')->assertForbidden();
        $this->putJson('/admin/api/social-hub/installation',[])->assertNotFound();
        $this->postJson('/admin/api/social-hub/heartbeat')->assertForbidden();
        config(['services.social_hub.developer_user_ids'=>['1']]);
        $this->get('/admin/social-hub/installation')->assertNotFound();
        $this->getJson('/admin/api/social-hub/installation')->assertOk()->assertJsonMissingPath('installation.encrypted_secret');
        $this->putJson('/admin/api/social-hub/installation',[])->assertNotFound();
        $this->actingAs((new User)->forceFill(['id'=>2,'is_admin'=>true,'is_super_admin'=>true]));
        $this->getJson('/admin/api/social-hub/installation')->assertForbidden();
    }
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','services.facebook.client_id'=>'test-app','services.facebook.client_secret'=>'test-secret','services.facebook.redirect'=>'https://ecom.example.test/admin/social/facebook/callback']);
        DB::purge();
        $this->artisan('migrate',['--path'=>'database/migrations/social','--force'=>true]);
        DB::table('social_hub_settings')->insert(['id'=>1,'hub_url'=>'https://social.jwaistudio.online','client_id'=>'client-a','key_id'=>'key-a','encrypted_secret'=>Crypt::encryptString('installation-secret'),'registered_domain'=>'a.example.com','enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $u=new User;$u->forceFill(['id'=>1,'is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
        $this->transaction=(string)Str::uuid();Http::preventStrayRequests();
    }
    private function hub(bool $signed=true): void
    {
        Http::fake(function($r)use($signed){
            $path=parse_url($r->url(),PHP_URL_PATH);
            $d=str_ends_with($path,'/start')?['transaction_id'=>$this->transaction,'app_id'=>'test-app','launch_url'=>'https://social.jwaistudio.online/oauth/facebook/launch?ticket='.str_repeat('a',64)]
                :['transaction_id'=>$this->transaction,'app_id'=>'test-app','result'=>'select','pages'=>[['id'=>'111','name'=>'Page One']]];
            $body=json_encode($d);$h=collect($r->headers())->map(fn($x)=>$x[0])->all();
            return Http::response($body,200,$signed?['X-Social-Response-Signature'=>SocialProtocol::responseSignature($h,'installation-secret','client-to-hub',200,$body)]:[]);
        });
    }
    private function back(string $state): string
    {
        return '/admin/social/facebook/hub-return?state='.$state.'&transaction='.$this->transaction.'&handoff='.str_repeat('b',64);
    }
    public function test_missing_installation_and_denied_permission_cannot_fall_back_to_direct_meta_oauth(): void
    {
        Http::fake(['*'=>Http::response(['message'=>'oauth_not_permitted'],403)]);
        $this->get('/admin/social/facebook/authorize')->assertRedirect('/admin/social/facebook?facebook=hub_not_permitted');
        $this->assertNull(session('facebook_oauth'));
        Http::assertSentCount(1);
        DB::table('social_hub_settings')->update(['enabled'=>false]);
        $this->get('/admin/social/facebook/authorize')->assertRedirect('/admin/social/facebook?facebook=hub_not_configured');
        Http::assertSentCount(1);
    }
    public function test_signed_grant_returns_to_same_actor_and_stores_only_safe_picker_state(): void
    {
        $this->hub();
        $this->get('/admin/social/facebook/authorize')->assertRedirect('https://social.jwaistudio.online/oauth/facebook/launch?ticket='.str_repeat('a',64));
        $state=session('facebook_oauth.state');
        $this->get($this->back($state))->assertRedirect('/admin/social/facebook?facebook=select');
        $pending=json_decode(Crypt::decryptString(session('facebook_pending')),true);
        $this->assertSame($this->transaction,$pending['transaction']);
        $this->assertSame('111',$pending['pages'][0]['id']);
        $this->assertArrayNotHasKey('token',$pending);
        $this->get($this->back($state))->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        Http::assertSentCount(2);
    }
    public function test_unsigned_success_expired_state_changed_actor_and_changed_installation_fail_closed(): void
    {
        $this->hub(false);
        $this->get('/admin/social/facebook/authorize')->assertRedirect('/admin/social/facebook?facebook=hub_unavailable');
        Http::swap(new \Illuminate\Http\Client\Factory);$this->hub();
        $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        DB::table('social_hub_settings')->update(['key_id'=>'new-key']);
        $this->get($this->back($state))->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        Http::assertSentCount(1);
        DB::table('social_hub_settings')->update(['key_id'=>'key-a']);
        $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $u=new User;$u->forceFill(['id'=>2,'is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
        $this->get($this->back($state))->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        Http::assertSentCount(2);
        $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');$this->travel(11)->minutes();
        $this->get($this->back($state))->assertRedirect('/admin/social/facebook?facebook=invalid_state');Http::assertSentCount(3);$this->travelBack();
    }
    public function test_multiple_pages_are_saved_together_and_reconnect_does_not_duplicate(): void
    {
        (require database_path('migrations/2026_10_04_120000_create_managed_social_accounts.php'))->up();
        \Illuminate\Support\Facades\Schema::table('managed_social_accounts',function(\Illuminate\Database\Schema\Blueprint $t){$t->boolean('inbound_enabled')->default(false);$t->timestamp('inbound_verified_at')->nullable();$t->string('messaging_error')->nullable();});
        $pages=[['id'=>'111','name'=>'Page One'],['id'=>'222','name'=>'Page Two']];
        $pending=['actor'=>1,'merchant'=>app(\App\Services\Social\FacebookAccountService::class)->merchant(),
            'app'=>'test-app','transaction'=>$this->transaction,'installation'=>app(\App\Services\Social\SocialHubOAuth::class)->fingerprint(),
            'expires'=>now()->addMinutes(5)->timestamp,'pages'=>$pages];
        $facebook=\Mockery::mock(\App\Services\Social\FacebookAccountService::class)->makePartial();
        $facebook->shouldReceive('check')->andReturnUsing(fn($a)=>['id'=>$a->id,'pageId'=>$a->provider_account_id]);
        $this->app->instance(\App\Services\Social\FacebookAccountService::class,$facebook);
        $subscription=\Mockery::mock(\App\Services\Social\MessengerSubscriptionService::class);
        $subscription->shouldReceive('activate')->times(4);$this->app->instance(\App\Services\Social\MessengerSubscriptionService::class,$subscription);
        Http::fake(function($r)use($pages){
            $this->assertSame(['111','222'],json_decode($r->body(),true)['page_ids']);
            $d=['transaction_id'=>$this->transaction,'app_id'=>'test-app','pages'=>array_map(fn($p)=>$p+['access_token'=>'private-'.$p['id']],$pages),'permissions'=>['pages_show_list']];
            $body=json_encode($d);$h=collect($r->headers())->map(fn($x)=>$x[0])->all();
            return Http::response($body,200,['X-Social-Response-Signature'=>SocialProtocol::responseSignature($h,'installation-secret','client-to-hub',200,$body)]);
        });
        $this->withSession(['facebook_pending'=>Crypt::encryptString(json_encode($pending))]);
        $this->postJson('/admin/api/social/facebook/select',['page_ids'=>['111','999']])->assertUnprocessable();Http::assertNothingSent();
        foreach(range(1,2) as $attempt){
            $this->withSession(['facebook_pending'=>Crypt::encryptString(json_encode($pending))]);
            $r=$this->postJson('/admin/api/social/facebook/select',['page_ids'=>['111','222']]);$r->assertOk()->assertJsonCount(2,'accounts')->assertJsonCount(0,'pages');
            $this->assertSame(2,DB::table('managed_social_accounts')->count());
            $this->assertNull(session('facebook_pending'));
        }
        $this->assertStringNotContainsString('private-',DB::table('managed_social_accounts')->value('access_token'));
    }


    public function test_instagram_denied_hub_never_redirects_to_meta(): void {
      Http::fake(['social.jwaistudio.online/*'=>Http::response(['message'=>'oauth_not_permitted'],403)]);
      $this->get('/admin/social/instagram/authorize')->assertRedirect('/admin/social/instagram?instagram=hub_not_permitted');
      $this->assertNull(session('instagram_oauth'));Http::assertSentCount(1);
    }
    public function test_instagram_launch_uses_signed_receipt_and_separate_client_state(): void {
      Http::fake(function($r){
        $body=json_encode(['transaction_id'=>$this->transaction,'app_id'=>'test-app','launch_url'=>'https://social.jwaistudio.online/oauth/instagram/launch?ticket='.str_repeat('a',64)]);
        $h=collect($r->headers())->map(fn($x)=>$x[0])->all();
        return Http::response($body,200,['X-Social-Response-Signature'=>SocialProtocol::responseSignature($h,'installation-secret','client-to-hub',200,$body)]);
      });
      $this->get('/admin/social/instagram/authorize')->assertRedirect('https://social.jwaistudio.online/oauth/instagram/launch?ticket='.str_repeat('a',64));
      $this->assertSame($this->transaction,session('instagram_oauth.transaction'));
      $this->assertNull(session('facebook_oauth'));Http::assertSent(fn($r)=>str_ends_with($r->url(),'/api/v1/oauth/instagram/start'));
    }
}
