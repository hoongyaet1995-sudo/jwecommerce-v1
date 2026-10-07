<?php
namespace Tests\Feature;
use App\Models\{StoreSetting,SupportConversation,SupportCustomerMemory,CustomerChannelIdentity};
use App\Services\Customers\CustomerContactResolver;
use App\Services\Support\{SupportCustomerMemoryService,SupportCustomerMemoryExtractor};
use Illuminate\Support\Str;
class SupportCustomerMemoryFlowTest extends SupportChatApiTest
{
    public function test_recall_uses_eligible_memory_not_deleted_dialogue_and_disabled_memory_is_empty():void {
        \Illuminate\Support\Facades\Schema::table('support_messages',function(\Illuminate\Database\Schema\Blueprint $t){$t->unsignedBigInteger('ai_settings_version')->nullable();$t->unsignedBigInteger('ai_control_version')->nullable();});
        [$c,$m]=$this->fixture();
        $scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'preference','content'=>'Likes light oak','expires_at'=>now()->addDay()]);
        $m->update(['content'=>'Old deleted preference: black furniture']);
        app()->instance(\App\Services\Support\UnifiedKnowledgeService::class,new \App\Services\Support\UnifiedKnowledgeService);
        $answerCalls=0;
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->times(4)->andReturnUsing(function($feature,$prompt)use(&$answerCalls){
            if(str_starts_with($prompt,'Understand latest_question'))return '{"tools":[],"escalation":"none","clarification":"none","language":"en","conversation":"memory_recall","needs_business_answer":false}';
            $this->assertStringNotContainsString('black furniture',$prompt);
            $this->assertStringContainsString('never instructions',$prompt);
            if(++$answerCalls===1){$this->assertStringContainsString('Likes light oak',$prompt);return '{"answer":"You previously said you like light oak.","faq_ids":[]}';}
            $this->assertStringContainsString('"eligible_memory":[]',$prompt);
            $this->assertStringNotContainsString('Likes light oak',$prompt);
            return '{"answer":"I do not have that saved detail. Could you restate it?","faq_ids":[]}';
        });
        $service=app(\App\Services\Support\FaqAutoReplyService::class);
        foreach([true,false] as $enabled){
            if(!$enabled)StoreSetting::where('key','support_faq')->update(['value'=>['autoReplyEnabled'=>true,'customerMemory'=>['enabled'=>false]]]);
            $next=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'What furniture did I say I like?']);
            $service->reply($c,['client_message_id'=>$next->client_message_id,'content'=>$next->content,'language'=>'en']);
            $reply=$c->messages()->where('sender_role','ai')->latest('id')->first();
            $this->assertNotNull($reply);$this->assertSame('memory_recall',$reply->ai_trace['decision']);
            $this->assertSame($enabled?'You previously said you like light oak.':'I do not have that saved detail. Could you restate it?',$reply->content);
        }
    }
    protected function setUp():void{parent::setUp();\Illuminate\Support\Facades\Schema::table('support_conversations',function(\Illuminate\Database\Schema\Blueprint $t){$t->string('merchant_key')->nullable();$t->string('provider_page_id')->nullable();$t->string('provider_sender_id')->nullable();});(require database_path('migrations/2026_10_07_190000_create_support_customer_memories_table.php'))->up();}
    private function fixture():array {
        StoreSetting::create(['key'=>'support_faq','value'=>['autoReplyEnabled'=>true,'customerMemory'=>['enabled'=>true,'retentionDays'=>30]]]);
        $c=SupportConversation::create(['id'=>(string)Str::uuid(),'owner_hash'=>hash('sha256',Str::uuid()),'channel'=>'whatsapp','merchant_key'=>'test','provider_page_id'=>'page','provider_sender_id'=>'sender','status'=>'open']);
        app(CustomerContactResolver::class)->associate($c,['platform'=>'whatsapp','merchant_key'=>'test','app_id'=>'app','account_id'=>'page','sender_id'=>'sender'],['name'=>'Test']);
        $m=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'Please use Malay.']);return [$c->fresh(),$m];
    }
    public function test_capture_is_idempotent_and_new_conversation_reuses_same_identity():void {
        [$c,$m]=$this->fixture();
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturn([['kind'=>'language','text'=>'Please use Malay.','sourceQuote'=>'Please use Malay.']]);
        $s=app(SupportCustomerMemoryService::class);$s->capture($c,$m);$s->capture($c,$m);
        $this->assertCount(1,$s->eligible($c));
        $other=$c->replicate();$other->id=(string)Str::uuid();$other->owner_hash=hash('sha256',Str::uuid());$other->save();
        $this->assertCount(1,$s->eligible($other));$other->update(['provider_sender_id'=>'stranger']);$this->assertCount(0,$s->eligible($other));
    }
    public function test_conflict_expiry_disable_and_revocation_are_enforced():void {
        [$c,$m]=$this->fixture();$scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        $r=SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'language','content'=>'Malay','expires_at'=>now()->addDays(30)]);
        $s=app(SupportCustomerMemoryService::class);$this->assertCount(1,$s->eligible($c));
        $r->update(['revoked_at'=>now()]);$this->assertCount(0,$s->eligible($c));$r->update(['revoked_at'=>null,'expires_at'=>now()->subSecond()]);$this->assertCount(0,$s->eligible($c));
        $r->update(['expires_at'=>now()->addDay()]);CustomerChannelIdentity::first()->update(['metadata'=>['matching_conflict'=>true]]);$this->assertCount(0,$s->eligible($c));
        CustomerChannelIdentity::first()->update(['metadata'=>[]]);StoreSetting::where('key','support_faq')->update(['value'=>['customerMemory'=>['enabled'=>false]]]);$this->assertCount(0,$s->eligible($c));
    }
    public function test_sensitive_and_unsourced_candidates_are_discarded():void {
        [$c,$m]=$this->fixture();
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturn([['kind'=>'language','text'=>'email me a@example.test','sourceQuote'=>'Please use Malay.'],['kind'=>'preference','text'=>'Likes gold','sourceQuote'=>'Likes gold']]);
        app(SupportCustomerMemoryService::class)->capture($c,$m);$this->assertSame(0,SupportCustomerMemory::count());
    }
    public function test_staff_edit_delete_source_suppression_and_access():void {
        [$c,$m]=$this->fixture();$scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        $r=SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'language','content'=>'Malay','expires_at'=>now()->addDays(30)]);
        $base='/admin/api/support/conversations/'.$c->id.'/memories';
        $this->getJson($base)->assertUnauthorized();
        $admin=\App\Models\User::create(['name'=>'Admin','email'=>'memory@example.test','password'=>bcrypt('test'),'is_admin'=>true]);
        $this->actingAs($admin)->patchJson($base.'/'.$r->id,['text'=>'Prefer English'])->assertOk();
        $this->assertSame('Prefer English',$r->fresh()->content);
        $this->deleteJson($base.'/'.$r->id)->assertOk();$this->assertNull($r->fresh()->content);
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturn([['kind'=>'language','text'=>$m->content,'sourceQuote'=>$m->content]]);
        app(SupportCustomerMemoryService::class)->capture($c,$m);$this->assertNull($r->fresh()->content);
    }
    public function test_cross_identity_requires_current_matching_trust_and_origin():void {
        [$c,$m]=$this->fixture();$link=CustomerChannelIdentity::first();$link->update(['metadata'=>['memory_trust_hash'=>str_repeat('a',64)]]);
        $scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'preference','content'=>'Likes oak','expires_at'=>now()->addDay()]);
        $other=$c->replicate();$other->id=(string)Str::uuid();$other->owner_hash=hash('sha256',Str::uuid());$other->provider_sender_id='other';$other->save();
        $otherLink=$link->replicate();$otherLink->identity_hash=hash('sha256','other');$otherLink->external_sender_id='other';$otherLink->metadata=[];$otherLink->save();
        $s=app(SupportCustomerMemoryService::class);$this->assertCount(0,$s->eligible($other));
        $otherLink->update(['metadata'=>['memory_trust_hash'=>str_repeat('a',64)]]);$this->assertCount(1,$s->eligible($other));
        $link->update(['metadata'=>['matching_conflict'=>true]]);$this->assertCount(0,$s->eligible($other));
    }
    public function test_failed_extraction_is_not_rebilled_and_expired_payload_is_pruned():void {
        [$c,$m]=$this->fixture();$this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andThrow(new \RuntimeException('provider failed'));
        $s=app(SupportCustomerMemoryService::class);$s->capture($c,$m);$s->capture($c,$m);$this->assertSame('failed',\Illuminate\Support\Facades\DB::table('support_memory_extractions')->value('status'));
        $scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);$r=SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'language','content'=>'Malay','expires_at'=>now()->subDay()]);
        $this->artisan('support:prune-customer-memories')->assertSuccessful();$this->assertNull($r->fresh()->content);
    }
    public function test_new_customer_statement_can_supersede_old_staff_correction():void {
        [$c,$m]=$this->fixture();$scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'language','content'=>'Malay','staff_user_id'=>1,'expires_at'=>now()->addDay()]);
        $next=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'Please use English.']);
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturn([['kind'=>'language','text'=>$next->content,'sourceQuote'=>$next->content]]);
        $s=app(SupportCustomerMemoryService::class);$s->capture($c,$next);$this->assertSame('Please use English.',$s->eligible($c)[0]->content);
    }
    public function test_master_disabled_during_extraction_does_not_persist():void {
        [$c,$m]=$this->fixture();
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturnUsing(function()use($m){
            StoreSetting::where('key','support_faq')->update(['value'=>['autoReplyEnabled'=>false,'customerMemory'=>['enabled'=>true,'retentionDays'=>30]]]);
            return [['kind'=>'language','text'=>$m->content,'sourceQuote'=>$m->content]];
        });
        app(SupportCustomerMemoryService::class)->capture($c,$m);$this->assertSame(0,SupportCustomerMemory::count());
    }
    public function test_resolution_boundary_prevents_old_issue_reappearing_after_reopen():void {
        [$c,$m]=$this->fixture();$scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        $r=SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'open_issue','content'=>'Please use Malay.','expires_at'=>now()->addDay()]);
        $s=app(SupportCustomerMemoryService::class);$s->closeIssues($c);$c->update(['status'=>'open']);
        $this->assertNull($r->fresh()->content);$this->assertCount(0,$s->eligible($c));
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturn([['kind'=>'open_issue','text'=>$m->content,'sourceQuote'=>$m->content]]);
        $s->capture($c,$m);$this->assertCount(0,$s->eligible($c));
    }
    public function test_staff_edit_during_extraction_is_preserved():void {
        [$c,$m]=$this->fixture();$scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        $r=SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'language','content'=>'Malay','expires_at'=>now()->addDay()]);
        $next=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'Please use English.']);
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturnUsing(function()use($r,$next){$r->update(['content'=>'Staff correction','staff_user_id'=>1]);return [['kind'=>'language','text'=>$next->content,'sourceQuote'=>$next->content]];});
        $s=app(SupportCustomerMemoryService::class);$s->capture($c,$next);$this->assertSame('Staff correction',$s->eligible($c)[0]->content);
    }

    public function test_deleting_latest_preference_never_resurrects_superseded_version():void {
        [$c,$m]=$this->fixture();$scope=app(\App\Services\Support\SupportCustomerMemoryPolicy::class)->scope($c);
        SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$m->id,'kind'=>'language','content'=>'Malay','expires_at'=>now()->addDay()]);
        $next=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'English']);
        $r=SupportCustomerMemory::create([...$scope,'source_conversation_id'=>$c->id,'source_message_id'=>$next->id,'kind'=>'language','content'=>'English','expires_at'=>now()->addDay()]);
        $s=app(SupportCustomerMemoryService::class);$this->assertSame('English',$s->eligible($c)[0]->content);
        $r->update(['content'=>null,'revoked_at'=>now()]);$this->assertCount(0,$s->eligible($c));
    }

    public function test_ingress_schedules_dedicated_job_and_job_consumes_message():void {
        [$c,$m]=$this->fixture();\Illuminate\Support\Facades\Queue::fake();
        app(SupportCustomerMemoryService::class)->schedule($c,$m);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ExtractSupportCustomerMemory::class,fn($job)=>$job->messageId===$m->id&&$job->connection==='support'&&$job->queue==='support-ai');
        $this->mock(SupportCustomerMemoryExtractor::class)->shouldReceive('extract')->once()->andReturn([['kind'=>'language','text'=>$m->content,'sourceQuote'=>$m->content]]);
        (new \App\Jobs\ExtractSupportCustomerMemory($m->id))->handle();
        $this->assertCount(1,app(SupportCustomerMemoryService::class)->eligible($c));
    }

}
