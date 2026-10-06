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
        Schema::create('customers',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable();$t->string('email')->nullable()->unique();$t->string('phone')->nullable();$t->string('first_name')->nullable();$t->string('last_name')->nullable();$t->string('avatar')->nullable();$t->string('status')->nullable();$t->json('tags')->nullable();$t->text('notes')->nullable();$t->timestamps();});
        Schema::create('store_settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->json('value');$t->timestamps();});
        foreach(['2026_10_02_160000_create_support_chat_tables.php','2026_10_03_100000_add_support_message_actions.php','2026_10_03_180000_create_notifications_table.php','2026_10_04_120000_create_managed_social_accounts.php'] as $file)(require database_path('migrations/'.$file))->up();
        if(file_exists(database_path('migrations/2026_10_04_180000_add_messenger_inbound.php')))(require database_path('migrations/2026_10_04_180000_add_messenger_inbound.php'))->up();
        Schema::table('support_messages',function(Blueprint $t){$t->string('delivery_status',16)->nullable();$t->string('provider_message_id',512)->nullable();$t->string('send_error',255)->nullable();$t->unsignedInteger('ai_settings_version')->nullable();$t->unsignedBigInteger('ai_control_version')->nullable();});
        Schema::table('managed_social_accounts',function(Blueprint $t){$t->string('messaging_error',255)->nullable();});
        config(['services.facebook.inbound_routing_ready'=>true]);
  (require database_path('migrations/2026_10_06_150000_add_customer_channel_identity.php'))->up();
        (require database_path('migrations/2026_10_06_190000_create_support_attachments.php'))->up();
  (require database_path('migrations/2026_10_06_200000_add_support_audio.php'))->up();
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
    private function aiSettings(): void
    {
        \App\Models\StoreSetting::create(['key'=>'support_faq','value'=>['version'=>1,'autoReplyEnabled'=>true,'primaryLanguage'=>'en','productCatalog'=>['isEnabled'=>false], 'faq'=>['isEnabled'=>true,'items'=>[['id'=>'wash','question'=>'Wash?','answer'=>'Cold wash.','isActive'=>true]]]]]);
    }
    private function runAi(SupportConversation $c, string $text): void
    {
        $c->update(['status'=>'open']);$source=$c->messages()->first();$source->update(['content'=>$text]);
        (new \App\Jobs\ProcessMessengerSupportReply($source->id))->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\MessengerReplyService::class));
    }
    public function test_messenger_uses_shared_faq_and_sends_once_while_remaining_pending(): void
    {
        $c=$this->thread();$this->aiSettings();
        Http::fake(['graph.facebook.com/*/messages'=>Http::response(['recipient_id'=>'222','message_id'=>'ai-out'])]);
        $this->runAi($c,'Wash?');$this->runAi($c,'Wash?');
        $reply=$c->messages()->where('sender_role','ai')->firstOrFail();
        $this->assertSame('Cold wash.',$reply->content);$this->assertSame('sent',$reply->delivery_status);
        $this->assertSame('open',$c->fresh()->status);Http::assertSentCount(1);
    }
    public function test_explicit_request_uses_shared_handoff_and_notifies_once(): void
    {
        $c=$this->thread();$this->aiSettings();
        Http::fake(['graph.facebook.com/*/messages'=>Http::response(['recipient_id'=>'222','message_id'=>'handoff-out'])]);
        $this->runAi($c,'talk to a human');
        $this->assertSame('waiting_human',$c->fresh()->status);
        $this->assertSame(1,DB::table('notifications')->count());
        $this->assertSame('sent',$c->messages()->where('sender_role','ai')->first()->delivery_status);
    }
    public function test_generated_ai_cannot_send_after_takeover_or_a_newer_customer_message(): void
    {
        $c=$this->thread();$source=$c->messages()->first();
        $reply=$c->messages()->create(['client_message_id'=>(string)\Illuminate\Support\Str::uuid(),'sender_role'=>'ai','content'=>'AI draft']);
        app(\App\Services\Social\MessengerReplyService::class)->sendGenerated($reply,$source);Http::assertNothingSent();
        $c->update(['status'=>'open']);$c->messages()->create(['client_message_id'=>(string)\Illuminate\Support\Str::uuid(),'sender_role'=>'customer','content'=>'New question']);
        app(\App\Services\Social\MessengerReplyService::class)->sendGenerated($reply,$source);Http::assertNothingSent();
    }
    public function test_ai_timeout_remains_unknown_without_job_resending(): void
    {
        $c=$this->thread();$this->aiSettings();$attempts=0;
        Http::fake(function()use(&$attempts){$attempts++;throw new \Illuminate\Http\Client\ConnectionException('timeout');});
        $this->runAi($c,'Wash?');$this->runAi($c,'Wash?');
        $this->assertSame(1,$attempts);$this->assertSame('unknown',$c->messages()->where('sender_role','ai')->first()->delivery_status);
    }
    public function test_semantic_human_request_in_messenger_uses_existing_ai_intent_planner(): void
    {
        $c=$this->thread();$this->aiSettings();
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"tools":[],"escalation":"explicit_request"}');
        Http::fake(['graph.facebook.com/*/messages'=>Http::response(['recipient_id'=>'222','message_id'=>'semantic-out'])]);
        $this->runAi($c,'Could somebody from your team personally help me?');
        $this->assertSame('waiting_human',$c->fresh()->status);Http::assertSentCount(1);
    }
    public function test_ai_is_silent_during_human_mode_and_resumes_on_next_turn_after_hand_back(): void
    {
        $c=$this->thread();$this->aiSettings();$source=$c->messages()->first();$source->update(['content'=>'Wash?']);
        Http::fake(['graph.facebook.com/*/messages'=>Http::response(['recipient_id'=>'222','message_id'=>'resume-out'])]);
        $job=new \App\Jobs\ProcessMessengerSupportReply($source->id);
        $job->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\MessengerReplyService::class));Http::assertNothingSent();
        $this->patchJson('/admin/api/support/conversations/'.$c->id.'/status',['status'=>'open'])->assertOk();
        $source=$c->messages()->create(['client_message_id'=>(string)\Illuminate\Support\Str::uuid(),'sender_role'=>'customer','content'=>'Wash?']);
        (new \App\Jobs\ProcessMessengerSupportReply($source->id))->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\MessengerReplyService::class));
        Http::assertSentCount(1);$this->assertSame('open',$c->fresh()->status);
    }
    public function test_inbound_job_and_receipt_commit_atomically_on_same_database(): void
    {
        $c=$this->thread();$c->update(['status'=>'open']);
        Schema::create('jobs',function(Blueprint $t){$t->id();$t->string('queue');$t->longText('payload');$t->unsignedTinyInteger('attempts');$t->unsignedInteger('reserved_at')->nullable();$t->unsignedInteger('available_at');$t->unsignedInteger('created_at');});
        $payload=['object'=>'page','entry'=>[['id'=>'111','messaging'=>[['sender'=>['id'=>'222'],'recipient'=>['id'=>'111'],'message'=>['mid'=>'durable','text'=>'Wash?']]]]]];
        $service=app(\App\Services\Social\MessengerInboundService::class);
        $this->assertSame(1,$service->receive($payload));$this->assertSame(0,$service->receive($payload));
        $this->assertSame(2,DB::table('jobs')->count());$this->assertSame('support-ai',DB::table('jobs')->value('queue'));
        DB::beginTransaction();$payload['entry'][0]['messaging'][0]['message']['mid']='rollback';$service->receive($payload);DB::rollBack();
        $this->assertSame(2,DB::table('jobs')->count());$this->assertSame(1,DB::table('messenger_inbound_receipts')->count());$this->assertSame(1,DB::table('notifications')->count());
    }
    public function test_human_request_routes_internally_even_when_page_cannot_send(): void
    {
        $c=$this->thread();$this->aiSettings();ManagedSocialAccount::where('provider_account_id','111')->update(['state'=>'unknown']);
        $this->runAi($c,'talk to a human');
        $this->assertSame('waiting_human',$c->fresh()->status);$this->assertSame(1,DB::table('notifications')->count());Http::assertNothingSent();
        $this->assertSame('failed',$c->messages()->where('sender_role','ai')->first()->delivery_status);
    }
    public function test_master_pause_does_not_generate_or_requeue_explicit_handoff(): void
    {
        $c=$this->thread();$c->update(['status'=>'open']);$this->aiSettings();
        $settings=\App\Models\StoreSetting::where('key','support_faq')->first();$data=$settings->value;$data['autoReplyEnabled']=false;$settings->update(['value'=>$data]);
        $source=$c->messages()->first();$source->update(['content'=>'talk to a human']);
        $lock=\Illuminate\Support\Facades\Cache::lock('support-ai:'.$c->id,90);$this->assertTrue($lock->get());
        $queued=\Mockery::mock(\Illuminate\Contracts\Queue\Job::class);$queued->shouldNotReceive('release');
        $job=new \App\Jobs\ProcessMessengerSupportReply($source->id);$job->setJob($queued);
        try{$job->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\MessengerReplyService::class));}finally{$lock->release();}
        $this->assertSame('open',$c->fresh()->status);Http::assertNothingSent();
    }
    public function test_recovered_ai_draft_is_cancelled_after_settings_or_control_change(): void
    {
        $c=$this->thread();$this->aiSettings();$c->update(['status'=>'open']);$source=$c->messages()->first();$source->update(['content'=>'Wash?']);
        app(\App\Services\Support\FaqAutoReplyService::class)->reply($c,['content'=>'Wash?','client_message_id'=>$source->client_message_id]);
        $settings=\App\Models\StoreSetting::where('key','support_faq')->first();$data=$settings->value;$data['version']=2;$settings->update(['value'=>$data]);
        (new \App\Jobs\ProcessMessengerSupportReply($source->id))->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\MessengerReplyService::class));
        Http::assertNothingSent();$this->assertSame('failed',$c->messages()->where('sender_role','ai')->first()->delivery_status);
        $b=$this->thread('333');$b->update(['status'=>'open']);$source=$b->messages()->first();$source->update(['content'=>'Wash?']);
        app(\App\Services\Support\FaqAutoReplyService::class)->reply($b,['content'=>'Wash?','client_message_id'=>$source->client_message_id]);
        $this->patchJson('/admin/api/support/conversations/'.$b->id.'/status',['status'=>'human_mode'])->assertOk();
        $this->patchJson('/admin/api/support/conversations/'.$b->id.'/status',['status'=>'open'])->assertOk();
        (new \App\Jobs\ProcessMessengerSupportReply($source->id))->handle(app(\App\Services\Support\FaqAutoReplyService::class),app(\App\Services\Social\MessengerReplyService::class));
        Http::assertNothingSent();$this->assertSame('failed',$b->messages()->where('sender_role','ai')->first()->delivery_status);
    }
    public function test_first_reply_automatically_takes_over(): void
    {
        $c=$this->thread();$c->update(['status'=>'open']);
        Http::fake(['graph.facebook.com/*/messages'=>Http::response(['recipient_id'=>'222','message_id'=>'mid-first'])]);
        $this->send($c,(string)\Illuminate\Support\Str::uuid())->assertOk()->assertJsonPath('sendStatus','sent');
        $this->assertSame('human_mode',$c->fresh()->status);
        $this->assertSame(1,$c->notes()->count());
    }
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
        $c->update(['status'=>'resolved']);$this->send($c,$id)->assertConflict();
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
        $this->artisan('migrate',['--path'=>'database/migrations/social','--force'=>true]);
        DB::table('social_hub_settings')->insert(['id'=>1,'hub_url'=>'https://social.jwaistudio.online','client_id'=>'client-a','key_id'=>'key-a',
            'encrypted_secret'=>\Illuminate\Support\Facades\Crypt::encryptString('installation-secret'),'registered_domain'=>'ecom.example.test','enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $hubResponse=function($r,string $token){
            $d=json_decode($r->body(),true);$body=json_encode(['app_id'=>'app-test','transaction_id'=>$d['transaction_id'],
                'page'=>['id'=>'111','name'=>'Selected','access_token'=>$token],'permissions'=>['pages_show_list','pages_messaging','pages_manage_metadata']]);
            $h=collect($r->headers())->map(fn($v)=>$v[0])->all();
            return Http::response($body,200,['X-Social-Response-Signature'=>\App\Services\Social\SocialProtocol::responseSignature($h,'installation-secret','client-to-hub',200,$body)]);
        };
        $subscribed=false;
        Http::fake(function($r)use(&$subscribed,$hubResponse){
            if(str_contains($r->url(),'social.jwaistudio.online'))return $hubResponse($r,'page-token');
            if(str_contains($r->url(),'/me/permissions'))return Http::response(['data'=>array_map(fn($p)=>['permission'=>$p,'status'=>'granted'],['pages_show_list','pages_messaging','pages_manage_metadata'])]);
            if(str_contains($r->url(),'/me/accounts'))return Http::response(['data'=>[['id'=>'111','name'=>'Selected','access_token'=>'page-token']]]);
            if(str_contains($r->url(),'/subscribed_apps')){
                if($r->method()==='POST'){$subscribed=true;return Http::response(['success'=>true]);}
                return Http::response(['data'=>[['id'=>'app-test','subscribed_fields'=>$subscribed?['feed','messages']:['feed']]]]);
            }
            return Http::response(['id'=>'111','name'=>'Selected']);
        });
        $pending=\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['actor'=>$staff->id,'merchant'=>'shop-test','app'=>'app-test','expires'=>now()->addMinutes(5)->timestamp,
            'installation'=>app(\App\Services\Social\SocialHubOAuth::class)->fingerprint(),'transaction'=>'12345678-1234-4234-8234-123456789abc','pages'=>[['id'=>'111','name'=>'Selected']]]));
        $this->withSession(['facebook_pending'=>$pending])->postJson('/admin/api/social/facebook/select',['page_id'=>'111'])->assertOk()->assertJsonPath('accounts.0.messagingReady',true);
        $this->assertTrue($subscribed);$this->assertTrue(ManagedSocialAccount::first()->inbound_enabled);
        Http::assertSent(fn($r)=>str_contains($r->url(),'/111/subscribed_apps')&&$r->method()==='POST'&&$r['subscribed_fields']==='feed,messages');
        Http::swap(new \Illuminate\Http\Client\Factory());Http::preventStrayRequests();
        Http::fake(function($r)use($hubResponse){
            if(str_contains($r->url(),'social.jwaistudio.online'))return $hubResponse($r,'new-token');
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

    private function instagramThread(): SupportConversation {
      $c=$this->thread();$c->update(['channel'=>'instagram']);
      ManagedSocialAccount::where('provider_account_id','111')->update(['platform'=>'instagram','permissions'=>json_encode(['instagram_manage_messages','pages_manage_metadata'])]);
      config(['services.instagram.inbound_routing_ready'=>true]);return $c->fresh();
    }
    public function test_instagram_subscription_uses_linked_page_and_preserves_existing_fields(): void {
      Schema::table('managed_social_accounts',fn(Blueprint $t)=>$t->string('linked_page_id',100)->nullable());
      $this->instagramThread();$account=ManagedSocialAccount::first();$account->forceFill(['linked_page_id'=>'555','inbound_enabled'=>false,'inbound_verified_at'=>null])->save();
      $this->assertSame('555',(string)$account->fresh()->linked_page_id);
      $reads=0;
      Http::fake(function($request)use(&$reads){
        $this->assertStringContainsString('/555/subscribed_apps',$request->url());
        if($request->method()==='POST'){
          $this->assertStringContainsString('feed',$request['subscribed_fields']);
          $this->assertStringContainsString('messages',$request['subscribed_fields']);
          return Http::response(['success'=>true]);
        }
        return Http::response(['data'=>[['id'=>'app-test','subscribed_fields'=>++$reads===1?['feed']:['feed','messages']]]]);
      });
      app(\App\Services\Social\InstagramSubscriptionService::class)->enable($account);
      $this->assertTrue($account->fresh()->inbound_enabled);Http::assertSentCount(3);
    }
    public function test_instagram_uses_shared_ai_and_explicit_handoff_then_staff_reply(): void {
      $c=$this->instagramThread();$this->aiSettings();
      Http::fake(fn($request)=>$request->url()==='https://graph.facebook.com/v25.0/me/messages'
        && $request->hasHeader('Authorization','Bearer token-111')
        && $request['recipient']['id']==='222'
        ? Http::response(['recipient_id'=>'222','message_id'=>'ig-out'])
        : Http::response(['error'=>['code'=>3,'message'=>'Wrong Facebook Login messaging endpoint']],400));
      $this->runAi($c,'Wash?');
      $this->assertSame('open',$c->fresh()->status);
      $this->assertSame('sent',$c->messages()->where('sender_role','ai')->firstOrFail()->delivery_status);
      $c->messages()->delete();$c->messages()->create(['client_message_id'=>(string)\Illuminate\Support\Str::uuid(),'sender_role'=>'customer','content'=>'talk to a human']);
      $this->runAi($c,'talk to a human');$this->assertSame('waiting_human',$c->fresh()->status);
      $this->send($c,(string)\Illuminate\Support\Str::uuid())->assertOk();
      $this->assertSame('human_mode',$c->fresh()->status);Http::assertSentCount(3);
    }
    public function test_instagram_does_not_use_facebook_connection_and_respects_reply_window(): void {
      $c=$this->instagramThread();$c->messages()->update(['created_at'=>now()->subHours(25)]);
      $this->send($c,(string)\Illuminate\Support\Str::uuid())->assertStatus(409);Http::assertNothingSent();
      $c->messages()->update(['created_at'=>now()]);ManagedSocialAccount::query()->update(['platform'=>'facebook']);
      $this->send($c,(string)\Illuminate\Support\Str::uuid())->assertStatus(409);Http::assertNothingSent();
    }

    public function test_image_send_uses_attachment_and_replay_keeps_image_identity(): void
    {
        \Illuminate\Support\Facades\Storage::fake('support-private');
        $c=$this->thread();
        $a=$this->post('/admin/api/support/conversations/'.$c->id.'/attachments',['image'=>\Illuminate\Http\UploadedFile::fake()->image('screen.png')],['Accept'=>'application/json'])->assertCreated()->json('attachment');
        Http::fake(['*/messages'=>Http::response(['recipient_id'=>'222','message_id'=>'image-out'])]);
        $data=['content'=>'','attachment_id'=>$a['id'],'client_message_id'=>(string)\Illuminate\Support\Str::uuid()];
        $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',$data)->assertOk()->assertJsonPath('sendStatus','sent');
        Http::assertSent(fn($r)=>$r['message']['attachment']['type']==='image' && str_contains($r['message']['attachment']['payload']['url'],'signature='));
        $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',$data)->assertOk();Http::assertSentCount(1);
        unset($data['attachment_id']);$data['content']='Changed';$this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',$data)->assertUnprocessable();
    }
    public function test_image_only_inbound_is_retained_without_ai_job(): void
    {
        \Illuminate\Support\Facades\Bus::fake();$this->thread();
        $p=['object'=>'page','entry'=>[['id'=>'111','messaging'=>[['sender'=>['id'=>'222'],'recipient'=>['id'=>'111'],'message'=>['mid'=>'image.in','attachments'=>[['type'=>'image','payload'=>['url'=>'https://scontent.xx.fbcdn.net/image.jpg']]]]]]]]];
        $service=app(\App\Services\Social\MessengerInboundService::class);
        $this->assertSame(1,$service->receive($p));$this->assertSame(0,$service->receive($p));
        $this->assertSame(1,\App\Models\SupportAttachment::count());
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\ProcessMessengerSupportReply::class);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\DownloadSupportImage::class);
    }
    public function test_same_image_reupload_cannot_bypass_unknown_send_protection(): void
    {
        \Illuminate\Support\Facades\Storage::fake('support-private');$c=$this->thread();
        $upload=fn()=> $this->post('/admin/api/support/conversations/'.$c->id.'/attachments',['image'=>\Illuminate\Http\UploadedFile::fake()->image('screen.png',20,20)],['Accept'=>'application/json'])->assertCreated()->json('attachment.id');
        $attempts=0;Http::fake(function()use(&$attempts){$attempts++;throw new \Illuminate\Http\Client\ConnectionException('timeout');});
        $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',['content'=>'','attachment_id'=>$upload(),'client_message_id'=>(string)\Illuminate\Support\Str::uuid()])->assertOk()->assertJsonPath('sendStatus','unknown');
        $this->postJson('/admin/api/support/conversations/'.$c->id.'/messages',['content'=>'','attachment_id'=>$upload(),'client_message_id'=>(string)\Illuminate\Support\Str::uuid()])->assertStatus(409);
        $this->assertSame(1,$attempts);
    }
    public function test_incoming_image_download_is_private_and_failure_is_visible(): void
    {
        \Illuminate\Support\Facades\Storage::fake('support-private');
        \Illuminate\Support\Facades\Bus::fake();
        $c=$this->thread();$account=ManagedSocialAccount::first();
        $message=$c->messages()->create(['client_message_id'=>(string)\Illuminate\Support\Str::uuid(),'sender_role'=>'customer','content'=>'']);
        $media=app(\App\Services\Support\SupportAttachmentService::class);
        $media->incoming($c,$message,$account,'https://scontent.xx.fbcdn.net/image.png');
        $a=$message->attachments()->firstOrFail();
        $fixture=\Illuminate\Http\UploadedFile::fake()->image('safe.png',20,20);$bytes=file_get_contents($fixture->getRealPath());
        $download=\Mockery::mock(\App\Services\Support\SupportMediaDownload::class);
        $download->shouldReceive('download')->once()->with('https://scontent.xx.fbcdn.net/image.png',null)->andReturn($bytes);
        $job=new \App\Jobs\DownloadSupportImage($a->id);$job->handle($download,$media);
        $this->assertSame('ready',$a->fresh()->status);
        $this->assertNull($a->fresh()->provider_reference);
        \Illuminate\Support\Facades\Storage::disk('support-private')->assertExists($a->fresh()->path);
        $media->incoming($c,$message,$account,'https://scontent.xx.fbcdn.net/expired.png');
        $failed=$message->attachments()->where('status','pending')->firstOrFail();
        (new \App\Jobs\DownloadSupportImage($failed->id))->failed(new \RuntimeException('secret provider response'));
        $this->assertSame('failed',$failed->fresh()->status);
        $this->assertStringNotContainsString('secret',$failed->fresh()->error);
    }
}
