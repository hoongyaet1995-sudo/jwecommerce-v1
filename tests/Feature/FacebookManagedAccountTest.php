<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FacebookManagedAccountTest extends TestCase
{
    private string $oauthResult='select';
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
            'services.facebook.client_id'=>'test-app','services.facebook.client_secret'=>'test-secret',
            'services.facebook.redirect'=>'https://ecom.example.test/admin/social/facebook/callback',
            'services.facebook.merchant_key'=>'shop-one']);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(false);$t->boolean('is_super_admin')->default(false);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
        (require database_path('migrations/2026_10_02_160000_create_support_chat_tables.php'))->up();
        (require database_path('migrations/2026_10_04_120000_create_managed_social_accounts.php'))->up();
        Schema::table('managed_social_accounts',function(Blueprint $t){$t->boolean('inbound_enabled')->default(false);$t->timestamp('inbound_verified_at')->nullable();$t->timestamp('last_inbound_at')->nullable();$t->string('messaging_error',255)->nullable();});
        Schema::table('support_conversations',function(Blueprint $t){$t->string('channel')->default('web_guest');$t->string('merchant_key')->nullable();$t->string('provider_page_id')->nullable();});
        Http::preventStrayRequests();
        $this->artisan('migrate',['--path'=>'database/migrations/social','--force'=>true]);
        DB::table('social_hub_settings')->insert(['id'=>1,'hub_url'=>'https://social.jwaistudio.online','client_id'=>'client-a','key_id'=>'key-a',
            'encrypted_secret'=>\Illuminate\Support\Facades\Crypt::encryptString('installation-secret'),'registered_domain'=>'ecom.example.test','enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->hubProvider();
    }

    private function hubProvider(): void
    {
        Http::fake(['social.jwaistudio.online/api/v1/oauth/facebook/*'=>function($r){
            $d=json_decode($r->body(),true);$action=basename(parse_url($r->url(),PHP_URL_PATH));
            $tx='12345678-1234-4234-8234-123456789abc';
            $reply=['app_id'=>'test-app','transaction_id'=>$tx]+match($action){
                'start'=>['launch_url'=>'https://social.jwaistudio.online/oauth/facebook/launch?ticket='.str_repeat('a',64)],
                'redeem'=>['result'=>$this->oauthResult,'pages'=>$this->oauthResult==='select'?[['id'=>'page-1','name'=>'Real Page']]:[]],
                'select'=>['page'=>['id'=>'page-1','name'=>'Real Page','access_token'=>'private-page-token','tasks'=>['MANAGE']],'permissions'=>['pages_show_list']],
            };
            $body=json_encode($reply);$h=collect($r->headers())->map(fn($v)=>$v[0])->all();
            return Http::response($body,200,['X-Social-Response-Signature'=>\App\Services\Social\SocialProtocol::responseSignature($h,'installation-secret','client-to-hub',200,$body)]);
        }]);
    }

    private function returnUrl(string $state): string
    {
        return '/admin/social/facebook/hub-return?state='.urlencode($state).'&transaction=12345678-1234-4234-8234-123456789abc&handoff='.str_repeat('b',64);
    }

    private function owner(): User
    {
        $user=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $this->actingAs($user); return $user;
    }

    private function provider(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*'=>Http::response(['access_token'=>'private-user-token','expires_in'=>3600]),
            'graph.facebook.com/*/me/permissions*'=>Http::response(['data'=>[['permission'=>'pages_show_list','status'=>'granted']]]),
            'graph.facebook.com/*/me/accounts*'=>Http::response(['data'=>[['id'=>'page-1','name'=>'Real Page','access_token'=>'private-page-token','tasks'=>['MANAGE']]]]),
            'graph.facebook.com/*/page-1*'=>Http::response(['id'=>'page-1','name'=>'Real Page']),
        ]);
    }

    private function completeOAuth(): void
    {
        $this->get('/admin/social/facebook/authorize')->assertRedirect();
        $state=session('facebook_oauth.state');
        $this->get($this->returnUrl($state))->assertRedirect('/admin/social/facebook?facebook=select');
    }

    public function test_authorization_enters_hub_and_never_direct_meta_oauth(): void
    {
        $this->owner();
        $response=$this->get('/admin/social/facebook/authorize')->assertRedirect();
        $this->assertStringStartsWith('https://social.jwaistudio.online/oauth/facebook/launch?ticket=',$response->headers->get('Location'));
        Http::assertNotSent(fn($r)=>str_contains($r->url(),'graph.facebook.com'));
    }

    public function test_empty_page_list_distinguishes_missing_business_grant_from_no_accessible_assets(): void
    {
        $this->owner();
        foreach ([false,true] as $businessGranted) {
            Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
            $permissions=[['permission'=>'pages_show_list','status'=>'granted']];
            if($businessGranted)$permissions[]=['permission'=>'business_management','status'=>'granted'];
            Http::fake([
                'graph.facebook.com/*/oauth/access_token*'=>Http::response(['access_token'=>'private-user-token']),
                'graph.facebook.com/*/me/permissions*'=>Http::response(['data'=>$permissions]),
                'graph.facebook.com/*/me/accounts*'=>Http::response(['data'=>[]]),
            ]);
            $this->oauthResult=$businessGranted?'no_pages':'business_permission_required';$this->hubProvider();
            $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
            $this->get($this->returnUrl($state))->assertRedirect('/admin/social/facebook?facebook='.$this->oauthResult);
            $this->assertSame(0,DB::table('managed_social_accounts')->count());
        }
    }

    public function test_real_provider_selection_is_encrypted_private_and_shared_by_shop_staff(): void
    {
        $this->owner();$this->provider();$this->completeOAuth();
        $this->getJson('/admin/api/social/facebook')->assertJsonPath('pages.0.name','Real Page')->assertDontSee('private-user-token')->assertDontSee('private-page-token');
        $this->postJson('/admin/api/social/facebook/select',['page_id'=>'page-1'])->assertOk()->assertJsonPath('accounts.0.state','connected');
        $stored=DB::table('managed_social_accounts')->first();
        $this->assertNotSame('private-page-token',$stored->access_token);
        $this->assertStringNotContainsString('private-page-token',$stored->access_token);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $this->getJson('/admin/api/social/facebook')->assertJsonPath('accounts.0.pageId','page-1')->assertDontSee('private-page-token');
        config(['services.facebook.merchant_key'=>'another-shop']);
        $this->getJson('/admin/api/social/facebook')->assertJsonCount(0,'accounts');
    }

    public function test_state_replay_wrong_state_and_expiry_fail_before_provider_call(): void
    {
        $this->owner();$this->provider();
        $this->get('/admin/social/facebook/authorize');
        $sent=count(Http::recorded());
        $this->get($this->returnUrl('wrong'))->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        $this->assertCount($sent,Http::recorded());
        $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $sent=count(Http::recorded());
        $this->travel(11)->minutes();
        $this->get($this->returnUrl($state))->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        $this->assertCount($sent,Http::recorded());
        $this->travelBack();$this->completeOAuth();$sent=count(Http::recorded());
        $this->get($this->returnUrl(session('facebook_oauth.state','')))->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        $this->assertCount($sent,Http::recorded());
    }

    public function test_selection_rejects_forged_page_and_denied_permission(): void
    {
        $this->owner();$this->provider();$this->completeOAuth();
        $this->postJson('/admin/api/social/facebook/select',['page_id'=>'another-page'])->assertUnprocessable();
        $this->assertSame(0,DB::table('managed_social_accounts')->count());
    }

    public function test_non_admin_cannot_connect_and_missing_config_fails_closed(): void
    {
        $this->getJson('/admin/api/social/facebook')->assertUnauthorized();
        $this->owner();config(['services.facebook.client_secret'=>null]);
        $this->get('/admin/social/facebook/authorize')->assertRedirect('/admin/social/facebook?facebook=not_configured');
        Http::assertNothingSent();
    }

    public function test_revoked_authorization_and_network_failure_have_distinct_honest_states(): void
    {
        $this->owner();$this->provider();$this->completeOAuth();
        $this->postJson('/admin/api/social/facebook/select',['page_id'=>'page-1'])->assertOk();
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests(); Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['code'=>190,'message'=>'sensitive-provider-message']],400)]);
        $this->getJson('/admin/api/social/facebook')->assertOk()->assertJsonPath('accounts.0.state','authorization_expired')->assertDontSee('sensitive-provider-message');
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests(); Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['code'=>2]],503)]);
        $this->getJson('/admin/api/social/facebook')->assertOk()->assertJsonPath('accounts.0.state','unknown');
    }

    public function test_actor_and_configuration_changes_reject_pending_state_and_pages(): void
    {
        $this->owner();$this->provider();$this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $sent=count(Http::recorded());
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $this->get($this->returnUrl($state))->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        $this->assertCount($sent,Http::recorded());
        $this->completeOAuth();config(['services.facebook.client_id'=>'different-app']);
        $this->postJson('/admin/api/social/facebook/select',['page_id'=>'page-1'])->assertUnprocessable();
        $this->assertSame(0,DB::table('managed_social_accounts')->count());
    }

    public function test_cancelled_or_denied_authorization_cannot_persist_pages(): void
    {
        $this->owner();$this->provider();$this->oauthResult='cancelled';$this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $this->get($this->returnUrl($state))->assertRedirect('/admin/social/facebook?facebook=cancelled');
        Http::assertNotSent(fn($r)=>str_contains($r->url(),'graph.facebook.com'));
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests(); Http::fake(['graph.facebook.com/*/oauth/access_token*'=>Http::response(['access_token'=>'private-user-token']),
            'graph.facebook.com/*/me/permissions*'=>Http::response(['data'=>[['permission'=>'pages_show_list','status'=>'declined']]])]);
        $this->oauthResult='permission_required';$this->hubProvider();
        $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $this->get($this->returnUrl($state))->assertRedirect('/admin/social/facebook?facebook=permission_required');
        $this->assertNull(session('facebook_pending'));
    }
    public function test_permission_failure_reports_safe_actionable_reason_and_check_time(): void
    {
        $this->owner();$this->provider();$this->completeOAuth();
        $this->postJson('/admin/api/social/facebook/select',['page_id'=>'page-1']);
        Http::swap(new \Illuminate\Http\Client\Factory());Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['code'=>100,'message'=>"This endpoint requires the 'pages_read_engagement' permission. private-token"]],400)]);
        $r=$this->getJson('/admin/api/social/facebook')->assertOk()->assertJsonPath('accounts.0.state','permission_required')
            ->assertJsonPath('accounts.0.checkReason','Page read permission is missing. Reconnect and grant Page access.')->assertDontSee('private-token');
        $this->assertNotNull($r->json('accounts.0.checkedAt'));
    }
    public function test_disconnect_clears_token_delete_requires_disconnect_and_owner_scope(): void
    {
        $this->owner();$this->provider();$this->completeOAuth();
        $this->postJson('/admin/api/social/facebook/select',['page_id'=>'page-1']);$id=DB::table('managed_social_accounts')->value('id');
        $this->deleteJson('/admin/api/social/facebook/'.$id)->assertUnprocessable();
        config(['services.facebook.merchant_key'=>'other-shop']);
        $this->postJson('/admin/api/social/facebook/'.$id.'/disconnect')->assertNotFound();
        config(['services.facebook.merchant_key'=>'shop-one']);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]));
        $this->postJson('/admin/api/social/facebook/'.$id.'/disconnect')->assertForbidden();
        $this->owner();
        $this->postJson('/admin/api/social/facebook/'.$id.'/disconnect')->assertOk()->assertJsonPath('accounts.0.state','disconnected');
        $this->assertSame('',\App\Models\ManagedSocialAccount::findOrFail($id)->access_token);
        Http::swap(new \Illuminate\Http\Client\Factory());Http::preventStrayRequests();
        $this->getJson('/admin/api/social/facebook')->assertOk()->assertJsonPath('accounts.0.state','disconnected');Http::assertNothingSent();
        $this->deleteJson('/admin/api/social/facebook/'.$id)->assertOk()->assertJsonCount(0,'accounts');
    }

    public function test_inflight_check_cannot_reactivate_a_disconnected_account(): void
    {
        $this->owner();$this->provider();$this->completeOAuth();$this->postJson('/admin/api/social/facebook/select',['page_id'=>'page-1']);
        $account=\App\Models\ManagedSocialAccount::firstOrFail();
        Http::swap(new \Illuminate\Http\Client\Factory());Http::preventStrayRequests();
        Http::fake(function()use($account){$fresh=$account->fresh();$fresh->update(['state'=>'disconnected','access_token'=>'']);return Http::response(['id'=>'page-1','name'=>'Real Page']);});
        $result=app(\App\Services\Social\FacebookAccountService::class)->check($account);
        $this->assertSame('disconnected',$result['state']);$this->assertSame('',$account->fresh()->access_token);
    }

    public function test_manager_check_recovers_messaging_subscription_without_another_oauth(): void
    {
        $owner=$this->owner();config(['services.facebook.inbound_routing_ready'=>true]);
        $account=\App\Models\ManagedSocialAccount::create(['merchant_key'=>'shop-one','platform'=>'facebook','app_id'=>'test-app','provider_account_id'=>'111','name'=>'Page','access_token'=>'private-page-token','permissions'=>['pages_messaging','pages_manage_metadata'],'tasks'=>['MESSAGE'],'state'=>'connected','authorized_by'=>$owner->id,'inbound_enabled'=>false,'messaging_error'=>'Temporary failure']);
        $subscribed=false;
        Http::fake(function($request)use(&$subscribed){
            if(str_contains($request->url(),'/subscribed_apps')){
                if($request->method()==='POST'){$subscribed=true;return Http::response(['success'=>true]);}
                return Http::response(['data'=>$subscribed?[['id'=>'test-app','subscribed_fields'=>['messages']]]:[]]);
            }
            return Http::response(['id'=>'111','name'=>'Page']);
        });
        $this->postJson('/admin/api/social/facebook/'.$account->id.'/check')->assertOk()->assertJsonPath('account.messagingReady',true);
        $this->assertTrue($subscribed);
    }

    public function test_staff_read_check_does_not_activate_subscription(): void
    {
        $owner=$this->owner();config(['services.facebook.inbound_routing_ready'=>true]);
        $account=\App\Models\ManagedSocialAccount::create(['merchant_key'=>'shop-one','platform'=>'facebook','app_id'=>'test-app','provider_account_id'=>'111','name'=>'Page','access_token'=>'private-page-token','permissions'=>['pages_messaging','pages_manage_metadata'],'tasks'=>[],'state'=>'connected','authorized_by'=>$owner->id,'inbound_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]));
        Http::fake(['graph.facebook.com/*'=>Http::response(['id'=>'111','name'=>'Page'])]);
        $this->postJson('/admin/api/social/facebook/'.$account->id.'/check')->assertOk()->assertJsonPath('account.messagingReady',false);
        Http::assertNotSent(fn($request)=>$request->method()==='POST');
    }

    public function test_manager_retry_keeps_missing_permissions_not_ready_without_subscription_write(): void
    {
        $owner=$this->owner();config(['services.facebook.inbound_routing_ready'=>true]);
        $account=\App\Models\ManagedSocialAccount::create(['merchant_key'=>'shop-one','platform'=>'facebook','app_id'=>'test-app','provider_account_id'=>'111','name'=>'Page','access_token'=>'private-page-token','permissions'=>['pages_show_list'],'tasks'=>[],'state'=>'connected','authorized_by'=>$owner->id,'inbound_enabled'=>false]);
        Http::fake(['graph.facebook.com/*'=>Http::response(['id'=>'111','name'=>'Page'])]);
        $this->postJson('/admin/api/social/facebook/'.$account->id.'/check')->assertOk()->assertJsonPath('account.messagingReady',false)->assertJsonPath('account.messagingError','Review Facebook authorization and grant messaging and Page webhook permissions.')->assertDontSee('private-page-token');
        Http::assertNotSent(fn($request)=>$request->method()==='POST');
    }

    public function test_manager_listing_does_not_repair_missing_subscription(): void
    {
        $owner=$this->owner();config(['services.facebook.inbound_routing_ready'=>true]);
        \App\Models\ManagedSocialAccount::create(['merchant_key'=>'shop-one','platform'=>'facebook','app_id'=>'test-app','provider_account_id'=>'111','name'=>'Page','access_token'=>'private-page-token','permissions'=>['pages_messaging','pages_manage_metadata'],'tasks'=>[],'state'=>'connected','authorized_by'=>$owner->id,'inbound_enabled'=>false]);
        Http::fake(['graph.facebook.com/*'=>Http::response(['id'=>'111','name'=>'Page'])]);
        $this->getJson('/admin/api/social/facebook')->assertOk()->assertJsonPath('accounts.0.messagingReady',false);
        Http::assertSentCount(1);Http::assertNotSent(fn($request)=>$request->method()==='POST');
    }

}
