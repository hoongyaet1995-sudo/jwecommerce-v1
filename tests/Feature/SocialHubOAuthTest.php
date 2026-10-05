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
}
