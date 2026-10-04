<?php

namespace Tests\Feature;

use App\Models\ManagedSocialAccount;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessengerReplyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
            'services.facebook.client_id'=>'app-test','services.facebook.client_secret'=>'secret-test',
            'services.facebook.webhook_verify_token'=>'verify-test','services.facebook.merchant_key'=>'shop-test']);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(false);$t->boolean('is_super_admin')->default(false);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
        Schema::create('customers',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable();});
        Schema::create('store_settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->json('value');$t->timestamps();});
        foreach(['2026_10_02_160000_create_support_chat_tables.php','2026_10_03_100000_add_support_message_actions.php','2026_10_03_180000_create_notifications_table.php','2026_10_04_120000_create_managed_social_accounts.php'] as $file)(require database_path('migrations/'.$file))->up();
        if(file_exists(database_path('migrations/2026_10_04_180000_add_messenger_inbound.php')))(require database_path('migrations/2026_10_04_180000_add_messenger_inbound.php'))->up();
        Schema::table('support_messages',function(Blueprint $t){$t->string('delivery_status',16)->nullable();$t->string('provider_message_id',512)->nullable();$t->string('send_error',255)->nullable();});
        Schema::table('managed_social_accounts',function(Blueprint $t){$t->string('messaging_error',255)->nullable();});
        config(['services.facebook.inbound_routing_ready'=>true]);
        Http::preventStrayRequests();
    }

    private function thread(string $page='111'): SupportConversation
    {
        $staff=User::factory()->create(['is_admin'=>true]);$this->actingAs($staff);
        ManagedSocialAccount::create(['merchant_key'=>'shop-test','platform'=>'facebook','app_id'=>'app-test','provider_account_id'=>$page,'name'=>'Page '.$page,'access_token'=>'token-'.$page,'permissions'=>['pages_messaging','pages_manage_metadata'],'tasks'=>[],'state'=>'connected','inbound_enabled'=>true,'inbound_verified_at'=>now(),'authorized_by'=>$staff->id]);
        $c=SupportConversation::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'owner_hash'=>hash('sha256',$page),'channel'=>'facebook','merchant_key'=>'shop-test','provider_page_id'=>$page,'provider_sender_id'=>'222','provider_page_name'=>'Page '.$page,'status'=>'human_mode','last_message_at'=>now()]);
        $c->messages()->create(['client_message_id'=>(string)\Illuminate\Support\Str::uuid(),'sender_role'=>'customer','content'=>'Hello']);return $c;
    }
    private function send(SupportConversation $c,string $uuid,string $content='Reply')
    {return $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',['content'=>$content,'client_message_id'=>$uuid]);}
    public function test_reply_uses_exact_page_token_and_recipient_and_is_idempotent(): void
    {
        $a=$this->thread();$b=$this->thread('333');
        Http::fake(['graph.facebook.com/*/messages'=>Http::response(['recipient_id'=>'222','message_id'=>'mid-out'])]);
        $id=(string)\Illuminate\Support\Str::uuid();
        $this->send($b,$id)->assertOk()->assertJsonPath('sendStatus','sent');
        $this->send($b,$id)->assertOk()->assertJsonPath('sendStatus','sent');
        Http::assertSentCount(1);Http::assertSent(fn($r)=>$r->url()==='https://graph.facebook.com/v25.0/333/messages'&&$r->hasHeader('Authorization','Bearer token-333')&&$r['recipient']['id']==='222'&&$r['messaging_type']==='RESPONSE');
        $this->assertSame(1,$a->messages()->count());$this->assertSame(2,$b->messages()->count());
        $this->send($b,$id,'Changed')->assertUnprocessable();Http::assertSentCount(1);
    }
    public function test_takeover_window_archived_and_mapping_guards_never_call_provider(): void
    {
        $c=$this->thread();$id=(string)\Illuminate\Support\Str::uuid();
        $c->update(['status'=>'waiting_human']);$this->send($c,$id)->assertConflict();
        $c->update(['status'=>'human_mode']);$c->messages()->update(['created_at'=>now()->subHours(24)]);$this->send($c,$id)->assertConflict();
        $c->messages()->update(['created_at'=>now()]);$c->notes()->create(['content'=>'[Archive] true']);$this->send($c,$id)->assertConflict();
        $c->notes()->delete();$c->update(['merchant_key'=>'other-shop']);$this->send($c,$id)->assertConflict();
        Http::assertNothingSent();
    }
    public function test_provider_rejection_retains_failed_message_and_safe_error(): void
    {
        $c=$this->thread();Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['code'=>200,'message'=>'private-token must not leak']],403)]);
        $id=(string)\Illuminate\Support\Str::uuid();$this->send($c,$id)->assertOk()->assertJsonPath('sendStatus','failed')->assertDontSee('private-token');
        $this->assertSame('failed',$c->messages()->latest('id')->first()->delivery_status);
        $this->send($c,$id)->assertOk()->assertJsonPath('sendStatus','failed');Http::assertSentCount(1);
    }
    public function test_timeout_is_uncertain_and_cannot_be_resent(): void
    {
        $c=$this->thread();Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('private transport detail'));
        $id=(string)\Illuminate\Support\Str::uuid();$this->send($c,$id)->assertOk()->assertJsonPath('sendStatus','unknown')->assertDontSee('private transport');
        $this->send($c,$id)->assertOk()->assertJsonPath('sendStatus','unknown');
        $this->assertSame(2,$c->messages()->count());
    }
    public function test_page_selection_automatically_subscribes_and_permission_failure_is_not_ready(): void
    {
        $staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($staff);
        $subscribed=false;
        Http::fake(function($r)use(&$subscribed){
            if(str_contains($r->url(),'/me/permissions'))return Http::response(['data'=>array_map(fn($p)=>['permission'=>$p,'status'=>'granted'],['pages_show_list','pages_messaging','pages_manage_metadata'])]);
            if(str_contains($r->url(),'/me/accounts'))return Http::response(['data'=>[['id'=>'111','name'=>'Selected','access_token'=>'page-token']]]);
            if(str_contains($r->url(),'/subscribed_apps')){
                if($r->method()==='POST'){$subscribed=true;return Http::response(['success'=>true]);}
                return Http::response(['data'=>[['id'=>'app-test','subscribed_fields'=>$subscribed?['feed','messages']:['feed']]]]);
            }
            return Http::response(['id'=>'111','name'=>'Selected']);
        });
        $pending=\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['actor'=>$staff->id,'merchant'=>'shop-test','app'=>'app-test','expires'=>now()->addMinutes(10)->timestamp,'token'=>'user-token','pages'=>[['id'=>'111','name'=>'Selected']]]));
        $this->withSession(['facebook_pending'=>$pending])->postJson('/admin/api/social/facebook/select',['page_id'=>'111'])->assertOk()->assertJsonPath('accounts.0.messagingReady',true);
        $this->assertTrue($subscribed);$this->assertTrue(ManagedSocialAccount::first()->inbound_enabled);
        Http::assertSent(fn($r)=>str_contains($r->url(),'/111/subscribed_apps')&&$r->method()==='POST'&&$r['subscribed_fields']==='feed,messages');
        Http::swap(new \Illuminate\Http\Client\Factory());Http::preventStrayRequests();
        Http::fake(function($r){
            if(str_contains($r->url(),'/me/permissions'))return Http::response(['data'=>array_map(fn($p)=>['permission'=>$p,'status'=>'granted'],['pages_show_list','pages_messaging','pages_manage_metadata'])]);
            if(str_contains($r->url(),'/me/accounts'))return Http::response(['data'=>[['id'=>'111','name'=>'Selected','access_token'=>'new-token']]]);
            if(str_contains($r->url(),'/subscribed_apps'))return Http::response(['error'=>['code'=>200,'message'=>'private error']],403);
            return Http::response(['id'=>'111','name'=>'Selected']);
        });
        $this->withSession(['facebook_pending'=>$pending])->postJson('/admin/api/social/facebook/select',['page_id'=>'111'])->assertOk()->assertJsonPath('accounts.0.messagingReady',false)->assertDontSee('private error');
        $this->assertFalse(ManagedSocialAccount::first()->inbound_enabled);$this->assertNotNull(ManagedSocialAccount::first()->messaging_error);
    }
    public function test_explicit_retry_of_definite_rejection_reuses_the_same_record(): void
    {
        $c=$this->thread();$id=(string)\Illuminate\Support\Str::uuid();
        Http::fakeSequence()->push(['error'=>['code'=>4]],429)->push(['recipient_id'=>'222','message_id'=>'accepted-retry']);
        $this->send($c,$id)->assertJsonPath('sendStatus','failed');
        $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',['content'=>'Reply','client_message_id'=>$id,'retry'=>true])->assertOk()->assertJsonPath('sendStatus','sent');
        $this->assertSame(2,$c->messages()->count());Http::assertSentCount(2);
    }
    public function test_pending_and_uncertain_messages_cannot_trigger_a_second_send(): void
    {
        $c=$this->thread();$id=(string)\Illuminate\Support\Str::uuid();
        $c->messages()->create(['client_message_id'=>$id,'sender_role'=>'human_agent','staff_user_id'=>auth()->id(),'content'=>'Reply','delivery_status'=>'sending']);
        $this->send($c,$id)->assertOk()->assertJsonPath('sendStatus','sending');
        $c->messages()->where('client_message_id',$id)->update(['updated_at'=>now()->subMinute()]);
        $this->send($c,$id)->assertOk()->assertJsonPath('sendStatus','unknown');
        $this->send($c,(string)\Illuminate\Support\Str::uuid())->assertConflict();Http::assertNothingSent();
    }
    public function test_unavailable_page_app_permission_and_routing_block_sends(): void
    {
        $c=$this->thread();$id=(string)\Illuminate\Support\Str::uuid();$a=ManagedSocialAccount::first();
        $a->update(['app_id'=>'other-app']);$this->send($c,$id)->assertConflict();
        $a->update(['app_id'=>'app-test','permissions'=>[]]);$this->send($c,$id)->assertConflict();
        $a->update(['permissions'=>['pages_messaging'],'inbound_verified_at'=>null]);$this->send($c,$id)->assertConflict();
        $a->update(['inbound_verified_at'=>now()]);config(['services.facebook.inbound_routing_ready'=>false]);$this->send($c,$id)->assertConflict();
        Http::assertNothingSent();
    }
    public function test_page_readiness_changes_reach_existing_inbox_delta_without_refresh(): void
    {
        $c=$this->thread();$other=$this->thread('333');$a=ManagedSocialAccount::where('provider_account_id','111')->firstOrFail();
        $a->update(['inbound_enabled'=>false,'inbound_verified_at'=>null]);
        SupportConversation::query()->update(['updated_at'=>now()->subMinute()]);$since=now()->toIso8601String();$this->travel(2)->seconds();
        Http::fake(fn($r)=>str_contains($r->url(),'/subscribed_apps')?Http::response(['data'=>[['id'=>'app-test','subscribed_fields'=>['messages']]]]):Http::response(['id'=>basename(parse_url($r->url(),PHP_URL_PATH)),'name'=>'Verified Page']));
        app(\App\Services\Social\MessengerSubscriptionService::class)->enable($a);
        $this->getJson('/admin/api/support/conversations?since='.urlencode($since))->assertOk()->assertJsonCount(1,'conversations')->assertJsonPath('conversations.0.id',$c->id)->assertJsonPath('conversations.0.canReply',true);
        $this->assertTrue($other->fresh()->updated_at->lt(\Illuminate\Support\Carbon::parse($since)));
        $since=now()->toIso8601String();$this->travel(2)->seconds();$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $this->postJson('/admin/api/social/facebook/'.$a->id.'/disconnect')->assertOk();
        $this->getJson('/admin/api/support/conversations?since='.urlencode($since))->assertOk()->assertJsonCount(1,'conversations')->assertJsonPath('conversations.0.canReply',false);
    }
}
