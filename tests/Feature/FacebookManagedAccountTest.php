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
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
            'services.facebook.client_id'=>'test-app','services.facebook.client_secret'=>'test-secret',
            'services.facebook.redirect'=>'https://ecom.example.test/admin/social/facebook/callback',
            'services.facebook.merchant_key'=>'shop-one']);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(false);$t->boolean('is_super_admin')->default(false);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
        (require database_path('migrations/2026_10_04_120000_create_managed_social_accounts.php'))->up();
        Http::preventStrayRequests();
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
        $this->get('/admin/social/facebook/callback?state='.urlencode($state).'&code=test-code')->assertRedirect('/admin/social/facebook?facebook=select');
    }

    public function test_authorization_requests_business_page_discovery_and_rerequests_declined_scopes(): void
    {
        $this->owner();
        $response=$this->get('/admin/social/facebook/authorize')->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'),PHP_URL_QUERY),$query);
        $this->assertEqualsCanonicalizing(['pages_show_list','business_management','pages_read_engagement','pages_messaging','pages_manage_metadata'],explode(',',$query['scope']));
        $this->assertSame('rerequest',$query['auth_type']??null);
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
            $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
            $this->get('/admin/social/facebook/callback?state='.$state.'&code=test')->assertRedirect('/admin/social/facebook?facebook='.($businessGranted?'no_pages':'business_permission_required'));
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
        $this->get('/admin/social/facebook/callback?state=wrong&code=test')->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        Http::assertNothingSent();
        $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $this->travel(11)->minutes();
        $this->get('/admin/social/facebook/callback?state='.$state.'&code=test')->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        Http::assertNothingSent();
        $this->travelBack();$this->completeOAuth();$sent=count(Http::recorded());
        $this->get('/admin/social/facebook/callback?state='.urlencode(session('facebook_oauth.state','')).'&code=test')->assertRedirect('/admin/social/facebook?facebook=invalid_state');
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
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $this->get('/admin/social/facebook/callback?state='.$state.'&code=test')->assertRedirect('/admin/social/facebook?facebook=invalid_state');
        Http::assertNothingSent();
        $this->completeOAuth();config(['services.facebook.client_id'=>'different-app']);
        $this->postJson('/admin/api/social/facebook/select',['page_id'=>'page-1'])->assertUnprocessable();
        $this->assertSame(0,DB::table('managed_social_accounts')->count());
    }

    public function test_cancelled_or_denied_authorization_cannot_persist_pages(): void
    {
        $this->owner();$this->provider();$this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $this->get('/admin/social/facebook/callback?state='.$state.'&error=access_denied')->assertRedirect('/admin/social/facebook?facebook=cancelled');
        Http::assertNothingSent();
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests(); Http::fake(['graph.facebook.com/*/oauth/access_token*'=>Http::response(['access_token'=>'private-user-token']),
            'graph.facebook.com/*/me/permissions*'=>Http::response(['data'=>[['permission'=>'pages_show_list','status'=>'declined']]])]);
        $this->get('/admin/social/facebook/authorize');$state=session('facebook_oauth.state');
        $this->get('/admin/social/facebook/callback?state='.$state.'&code=test')->assertRedirect('/admin/social/facebook?facebook=permission_required');
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

}
