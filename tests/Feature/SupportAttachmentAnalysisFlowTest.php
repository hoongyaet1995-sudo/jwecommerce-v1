<?php
namespace Tests\Feature;
use App\Models\{StoreSetting,SupportAttachment,SupportAttachmentAnalysis,SupportConversation};
use App\Services\Support\{SupportAttachmentAnalyzer,SupportAttachmentAnalysisService};
use Illuminate\Support\Facades\{Queue,Storage};
use Illuminate\Support\Str;

class SupportAttachmentAnalysisFlowTest extends SupportChatApiTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_07_180000_create_support_attachment_analyses_table.php'))->up();
        Queue::fake(); Storage::fake('support-private');
    }
    private function media(): array
    {
        StoreSetting::create(['key'=>'support_faq','value'=>['version'=>1,'autoReplyEnabled'=>true,'attachmentAnalysis'=>['image'=>true]]]);
        $c=SupportConversation::create(['id'=>(string)Str::uuid(),'owner_hash'=>str_repeat('a',64),'status'=>'open','channel'=>'web_guest']);
        $source=$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'What is shown?']);
        Storage::disk('support-private')->put('test.png','bytes');
        $a=SupportAttachment::create(['id'=>(string)Str::uuid(),'conversation_id'=>$c->id,'message_id'=>$source->id,'owner_hash'=>$c->owner_hash,'kind'=>'image','name'=>'a.png','mime'=>'image/png','disk'=>'support-private','path'=>'test.png','sha256'=>hash('sha256','bytes'),'status'=>'ready']);
        return [$c,$source,$a];
    }
    public function test_scheduling_deduplicates_and_does_not_process_staff_files(): void
    {
        [$c,$source,$a]=$this->media(); $service=app(SupportAttachmentAnalysisService::class);
        $this->assertTrue($service->schedule($source)); $this->assertTrue($service->schedule($source));
        Queue::assertPushed(\App\Jobs\AnalyzeSupportAttachments::class,1);
        $source->update(['sender_role'=>'human_agent']); $this->assertFalse($service->schedule($source));
    }
    public function test_one_analysis_attempt_is_encrypted_and_duplicate_run_does_not_bill_again(): void
    {
        [$c,$source,$a]=$this->media();
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andReturn(['text'=>'Wooden chair','limitations'=>[],'needsConfirmation'=>false,'provider'=>'openai','model'=>'vision']);
        $service=app(SupportAttachmentAnalysisService::class);
        $this->assertTrue($service->process($source),json_encode(['sourceId'=>$source->id,'latest'=>$c->messages()->where('sender_role','customer')->max('id'),'attachment'=>$a->fresh()->only(['status','merchant_key','owner_hash']),'conversation'=>$c->fresh()->only(['status','merchant_key','owner_hash']),'analysis'=>SupportAttachmentAnalysis::first()?->only(['status','failure_code'])])); $this->assertTrue($service->process($source));
        $this->assertSame(1,SupportAttachmentAnalysis::count());
        $this->assertSame('Wooden chair',$service->context($source)['items'][0]['text']);
        $this->assertStringNotContainsString('Wooden chair',\Illuminate\Support\Facades\DB::table('support_attachment_analyses')->value('result'));
        $c->update(['status'=>'human_mode']); $this->assertFalse($service->process($source));
    }
    public function test_pending_download_and_newer_message_do_not_analyze_or_answer(): void
    {
        [$c,$source,$a]=$this->media(); $service=app(SupportAttachmentAnalysisService::class);
        $a->update(['status'=>'pending']); $this->assertFalse($service->process($source));
        $a->update(['status'=>'ready']);
        $c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'Actually wait']);
        $this->assertFalse($service->process($source)); $this->assertNull($service->context($source));
    }
    public function test_download_completion_schedules_analysis_and_caption_waits_for_result(): void
    {
        [$c,$source,$a]=$this->media();
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');
        $this->assertFalse(app(\App\Services\Support\FaqAutoReplyService::class)->reply($c,['content'=>$source->content,'client_message_id'=>$source->client_message_id]));
        $this->assertSame(1,$c->messages()->count());
    }
    public function test_failure_creates_one_safe_reply_without_llm_or_private_extraction(): void
    {
        [$c,$source,$a]=$this->media();
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andThrow(new \App\Services\AI\ProviderFailure('unavailable'));
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');
        app(SupportAttachmentAnalysisService::class)->process($source);
        $ai=app(\App\Services\Support\FaqAutoReplyService::class);
        $data=['content'=>$source->content,'client_message_id'=>$source->client_message_id];
        $ai->reply($c,$data); $ai->reply($c,$data);
        $this->assertSame(1,$c->messages()->where('sender_role','ai')->count());
        $this->assertStringContainsString('describe',$c->messages()->where('sender_role','ai')->first()->content);
    }
    public function test_cross_merchant_is_rejected_and_expired_analysis_is_not_disclosed(): void
    {
        [$c,$source,$a]=$this->media(); $service=app(SupportAttachmentAnalysisService::class);
        $a->update(['merchant_key'=>'another-merchant']);
        $this->assertFalse($service->process($source));$this->assertNull($service->context($source));
        $a->update(['merchant_key'=>null]);
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andReturn(['text'=>'Private image text','limitations'=>[],'needsConfirmation'=>false,'provider'=>'openai','model'=>'vision']);
        $service->process($source);$analysis=SupportAttachmentAnalysis::first();$analysis->update(['expires_at'=>now()->subDay()]);
        $this->assertNull($service->view($a)['text']);
        $this->artisan('support:prune-attachment-analyses')->assertSuccessful();
        $this->assertNull($analysis->fresh()->result);$this->assertSame('expired',$analysis->fresh()->status);
        Storage::disk('support-private')->assertExists('test.png');
    }
    public function test_staff_takeover_during_provider_call_prevents_answer_dispatch(): void
    {
        [$c,$source,$a]=$this->media();
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andReturnUsing(function()use($c){
            $c->update(['status'=>'human_mode']);return ['text'=>'Wooden chair','limitations'=>[],'needsConfirmation'=>false,'provider'=>'openai','model'=>'vision'];
        });
        app(\App\Jobs\AnalyzeSupportAttachments::class,['sourceId'=>$source->id])->handle(app(SupportAttachmentAnalysisService::class));
        Queue::assertNotPushed(\App\Jobs\ReplyToAnalyzedSupportAttachment::class);
        $this->assertSame('ready',SupportAttachmentAnalysis::first()->status);
    }
    public function test_analysis_notes_are_staff_only_and_retry_is_scoped(): void
    {
        [$c,$source,$a]=$this->media();
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andReturn(['text'=>'Private image text','limitations'=>[],'needsConfirmation'=>false,'provider'=>'openai','model'=>'vision']);
        app(SupportAttachmentAnalysisService::class)->process($source);
        $media=app(\App\Services\Support\SupportAttachmentService::class);
        $this->assertArrayNotHasKey('analysis',$media->view($source,false)[0]);
        $this->assertSame('Private image text',$media->view($source,true)[0]['analysis']['text']);
        $this->postJson('/admin/api/support/attachments/'.$a->id.'/analysis/retry')->assertUnauthorized();
        $staff=\App\Models\User::create(['name'=>'Staff','email'=>'analysis@example.test','password'=>bcrypt('test'),'is_admin'=>true]);
        $a->update(['merchant_key'=>'other']);
        $this->actingAs($staff)->postJson('/admin/api/support/attachments/'.$a->id.'/analysis/retry')->assertNotFound();
    }
    public function test_multiple_files_wait_for_all_analyses_before_dispatching_one_answer(): void
    {
        [$c,$source,$a]=$this->media();
        $second=$a->replicate();$second->id=(string)Str::uuid();$second->save();
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->twice()->andReturn(['text'=>'Table','limitations'=>[],'needsConfirmation'=>true,'provider'=>'openai','model'=>'vision']);
        $service=app(SupportAttachmentAnalysisService::class);
        $job=new \App\Jobs\AnalyzeSupportAttachments($source->id);
        $job->handle($service);
        Queue::assertNotPushed(\App\Jobs\ReplyToAnalyzedSupportAttachment::class);
        $job->handle($service);
        Queue::assertPushed(\App\Jobs\ReplyToAnalyzedSupportAttachment::class,1);
        $this->assertCount(2,$service->context($source)['items']);
    }
    public function test_worker_failure_finishes_attempt_without_rebilling_and_schedules_fallback(): void
    {
        [$c,$source,$a]=$this->media();
        app(SupportAttachmentAnalysisService::class)->schedule($source);
        SupportAttachmentAnalysis::query()->update(['status'=>'processing','started_at'=>now()]);
        (new \App\Jobs\AnalyzeSupportAttachments($source->id))->failed(new \RuntimeException('timeout'));
        $this->assertSame('interrupted',SupportAttachmentAnalysis::first()->failure_code);
        Queue::assertPushed(\App\Jobs\ReplyToAnalyzedSupportAttachment::class,1);
        $this->mock(SupportAttachmentAnalyzer::class)->shouldNotReceive('analyze');
        $this->assertTrue(app(SupportAttachmentAnalysisService::class)->process($source));
    }
    public function test_followup_context_uses_only_enabled_unexpired_customer_observations(): void
    {
        [$c,$source,$a]=$this->media();
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andReturn(['text'=>'Table corner is chipped','limitations'=>['Not proof of refund eligibility'],'needsConfirmation'=>true,'provider'=>'openai','model'=>'vision']);
        app(SupportAttachmentAnalysisService::class)->process($source);
        $c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>'What should I do about this?']);
        $context=app(\App\Services\Support\SupportDialogueContext::class);
        $this->assertStringContainsString('Table corner is chipped',implode(' ',$context->recent($c)));
        SupportAttachmentAnalysis::query()->update(['expires_at'=>now()->subSecond()]);
        $this->assertStringNotContainsString('Table corner is chipped',implode(' ',$context->recent($c)));
        SupportAttachmentAnalysis::query()->update(['expires_at'=>now()->addDay()]);
        StoreSetting::where('key','support_faq')->update(['value'=>['version'=>2,'autoReplyEnabled'=>true,'attachmentAnalysis'=>['image'=>false]]]);
        $this->assertStringNotContainsString('Table corner is chipped',implode(' ',$context->recent($c)));
    }
    public function test_web_reply_contention_releases_job_with_budget_exceeding_generation_lock(): void
    {
        [$c,$source,$a]=$this->media();
        $job=new \App\Jobs\ReplyToAnalyzedSupportAttachment($source->id);
        $queueJob=\Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldReceive('release')->once()->with(5);$job->setJob($queueJob);
        $ai=$this->mock(\App\Services\Support\FaqAutoReplyService::class);
        $ai->shouldReceive('reply')->once()->andReturn(false);
        $job->handle($ai);
        $this->assertGreaterThan(90,($job->tries-1)*5);
    }
    public function test_voice_requires_customer_confirmation_before_using_transcribed_parameters(): void
    {
        \Illuminate\Support\Facades\Schema::table('support_messages',function($table){$table->unsignedInteger('ai_settings_version')->nullable();$table->unsignedInteger('ai_control_version')->nullable();});
        [$c,$source,$a]=$this->media();$c->update(['channel'=>'whatsapp']);$a->update(['kind'=>'audio']);
        StoreSetting::where('key','support_faq')->update(['value'=>['version'=>2,'autoReplyEnabled'=>true,'attachmentAnalysis'=>['audio'=>true]]]);
        $this->mock(SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andReturn(['text'=>'Shipping to an uncertain destination','limitations'=>['Automatic transcript'],'needsConfirmation'=>true,'provider'=>'openai','model'=>'transcribe']);
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');
        app(SupportAttachmentAnalysisService::class)->process($source);
        app(\App\Services\Support\FaqAutoReplyService::class)->reply($c,['client_message_id'=>$source->client_message_id,'content'=>$source->content]);
        $this->assertStringContainsString('Please confirm',$c->messages()->where('sender_role','ai')->first()->content);
    }
    public function test_changed_private_file_is_rejected_before_any_provider_call(): void
    {
        [$c,$source,$a]=$this->media();$a->update(['sha256'=>str_repeat('0',64)]);
        $this->partialMock(SupportAttachmentAnalyzer::class,function($mock){
            $mock->shouldReceive('connection')->andReturn(new \App\Models\AiConnection(['provider'=>'openai','text_model'=>'gpt-5.6-luna']));
            $mock->shouldReceive('readiness')->andReturn(['image'=>true,'document'=>true,'audio'=>true]);
        });
        \Illuminate\Support\Facades\Http::fake();
        app(SupportAttachmentAnalysisService::class)->process($source);
        $this->assertSame('media_changed',SupportAttachmentAnalysis::first()->failure_code);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
    public function test_voice_over_analysis_duration_remains_transport_ready_without_provider_call(): void
    {
        [$c,$source,$a]=$this->media();$c->update(['channel'=>'whatsapp']);$a->update(['kind'=>'audio','duration_seconds'=>121]);
        StoreSetting::where('key','support_faq')->update(['value'=>['version'=>2,'autoReplyEnabled'=>true,'attachmentAnalysis'=>['audio'=>true,'audioSeconds'=>120]]]);
        $this->partialMock(SupportAttachmentAnalyzer::class,function($mock){
            $mock->shouldReceive('connection')->andReturn(new \App\Models\AiConnection(['provider'=>'openai','text_model'=>'gpt-5.6-luna']));
            $mock->shouldReceive('readiness')->andReturn(['image'=>true,'document'=>true,'audio'=>true]);
        });
        \Illuminate\Support\Facades\Http::fake();app(SupportAttachmentAnalysisService::class)->process($source);
        $this->assertSame('analysis_limit',SupportAttachmentAnalysis::first()->failure_code);
        $this->assertSame('ready',$a->fresh()->status);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
