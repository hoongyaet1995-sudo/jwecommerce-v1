<?php

namespace Tests\Feature;

use App\Jobs\ProcessEnquiryReception;
use App\Models\Product;
use App\Models\ProductEnquiry;
use App\Models\StoreSetting;
use App\Models\SupportConversation;
use App\Models\SupportEnquiryIntake;
use App\Models\User;
use App\Services\AI\AiConnectionService;
use App\Services\Support\EnquiryReception;
use App\Services\Support\EnquiryReceptionReply;
use App\Services\Support\SupportChatService;
use App\Services\SupportFaqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnquiryReceptionReplyTest extends TestCase
{
    use RefreshDatabase;

    private function thread(): SupportConversation
    {
        config(['product_enquiries.enabled' => true]);
        Queue::fake();
        Notification::fake();
        StoreSetting::create(['key' => 'support_faq', 'value' => ['version' => 1, 'autoReplyEnabled' => true, 'enquiryAccess' => ['isEnabled' => true], 'faq' => ['isEnabled' => true, 'retrievalDepth' => 'strict', 'items' => [['id' => 'delivery', 'question' => 'Where can delivery be arranged?', 'answer' => 'Delivery is subject to destination and site access confirmation.', 'isActive' => true]]]]]);
        $p = Product::create(['handle' => 'quoted-container', 'title' => 'Container', 'status' => 'active', 'purchase_mode' => 'quote', 'price_display' => 'hide', 'base_price' => 9000]);
        $p->pdpContent()->create(['faqs' => [['question' => 'Can I inspect it?', 'answer' => 'An inspection appointment can be discussed with our team.']]]);
        $c = SupportConversation::create(['id' => (string) Str::uuid(), 'owner_hash' => hash('sha256', Str::uuid()), 'status' => 'waiting_human']);
        ProductEnquiry::create(['id' => (string) Str::uuid(), 'reference' => 'TEST-REPLY', 'owner_hash' => hash('sha256', 'owner'), 'request_key' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'support_conversation_id' => $c->id, 'product_title' => 'Container', 'product_snapshot' => ['handle' => 'quoted-container', 'image' => '/images/themes/logistics/containers.png'], 'name' => 'Test', 'email' => 'test@example.test', 'requirements' => 'Need a container']);

        return $c;
    }

    private function source($c, $text)
    {
        return $c->messages()->create(['client_message_id' => (string) Str::uuid(), 'sender_role' => 'customer', 'content' => $text]);
    }

    public function test_enquiry_off_topic_is_redirected_without_collecting_or_handing_off():void{
        $c=$this->thread();$this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"topic":"off_topic","needs_business_answer":false,"answer":"","source_ids":[],"fields":{},"has_unanswered_question":false}');
        app(EnquiryReceptionReply::class)->reply($c,$this->source($c,'Is the moon rounder overseas?'));
        $reply=$c->messages()->where('sender_role','ai')->first();$this->assertSame('off_topic',$reply->ai_trace['decision']);$this->assertSame('waiting_human',$c->fresh()->status);$this->assertEmpty($reply->actions);$this->assertSame([],\App\Models\SupportEnquiryIntake::first()->unresolved);
    }
    public function test_waiting_unknown_then_known_faq_collects_evidence_and_preserves_unresolved(): void
    {
        $c = $this->thread();
        $r = app(EnquiryReceptionReply::class);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->twice()->andReturn(
            json_encode(['answer' => '', 'source_ids' => [], 'fields' => ['quantity' => ['value' => 'two', 'evidence' => 'Need two'], 'location' => ['value' => 'Singapore', 'evidence' => 'to Singapore']], 'has_unanswered_question' => true]),
            json_encode(['answer' => 'Delivery is subject to destination and site access confirmation.', 'source_ids' => ['delivery'], 'fields' => [], 'has_unanswered_question' => false]));
        $m = $this->source($c, 'Need two, can you deliver to Singapore?');
        $r->reply($c, $m);
        $r->reply($c, $m);
        $this->assertSame('two', SupportEnquiryIntake::first()->fields['quantity']['value']);
        $this->assertSame('waiting_human', $c->fresh()->status);
        $this->assertCount(1, SupportEnquiryIntake::first()->unresolved);
        $m = $this->source($c, 'Where can delivery be arranged?');
        $r->reply($c, $m);
        $this->assertSame(2, $c->messages()->where('sender_role', 'ai')->count());
        $this->assertStringContainsString('destination and site access', $c->messages()->latest('id')->first()->content);
        $this->assertCount(1, SupportEnquiryIntake::first()->unresolved);
    }

    public function test_takeover_during_generation_prevents_late_reply_and_field_commit(): void
    {
        $c = $this->thread();
        $m = $this->source($c, 'Need two');
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturnUsing(function () use ($c) {
            $c->update(['status' => 'human_mode']);
            $c->notes()->create(['content' => '[Control] waiting_human → human_mode']);

            return json_encode(['answer' => '', 'source_ids' => [], 'fields' => ['quantity' => ['value' => 'two', 'evidence' => 'Need two']]]);
        });
        app(EnquiryReceptionReply::class)->reply($c, $m);
        $this->assertSame(0, $c->messages()->where('sender_role', 'ai')->count());
        $this->assertArrayNotHasKey('quantity', SupportEnquiryIntake::first()->fields);
    }

    public function test_access_off_during_generation_blocks_delivery(): void
    {
        $c = $this->thread();
        $m = $this->source($c, 'Need two');
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturnUsing(function () {
            $s = StoreSetting::where('key', 'support_faq')->first();
            $v = $s->value;
            $v['enquiryAccess']['isEnabled'] = false;
            $s->update(['value' => $v]);

            return '{"answer":"","source_ids":[],"fields":{}}';
        });
        app(EnquiryReceptionReply::class)->reply($c, $m);
        $this->assertSame(0, $c->messages()->where('sender_role', 'ai')->count());
    }

    public function test_staff_reply_takes_over_and_manual_return_to_ai_is_audited(): void
    {
        $c = $this->thread();
        $u = User::factory()->create(['is_admin' => true]);
        app(SupportChatService::class)->append($c, ['client_message_id' => (string) Str::uuid(), 'content' => 'I will help you.'], 'human_agent', $u->id);
        $this->assertSame('human_mode', $c->fresh()->status);
        $this->assertFalse(app(EnquiryReception::class)->eligible($c->fresh(), app(SupportFaqService::class)->read()));
        $this->actingAs($u)->patchJson('/admin/api/support/conversations/'.$c->id.'/status', ['status' => 'open'])->assertOk();
        $this->assertTrue(app(EnquiryReception::class)->eligible($c->fresh(), app(SupportFaqService::class)->read()));
        $this->assertTrue($c->notes()->where('staff_user_id', $u->id)->where('content', 'like', '[Control]%')->exists());
    }

    public function test_explicit_stop_suspends_ai_and_unknown_fields_are_not_invented(): void
    {
        $c = $this->thread();
        $m = $this->source($c, 'Stop AI replies');
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        app(EnquiryReceptionReply::class)->reply($c, $m);
        $this->assertTrue(SupportEnquiryIntake::first()->ai_suspended);
        $this->assertSame(1, $c->messages()->where('sender_role', 'ai')->count());
        app(EnquiryReceptionReply::class)->reply($c, $this->source($c, 'Where can delivery be arranged?'));
        $this->assertSame(1, $c->messages()->where('sender_role', 'ai')->count());
    }

    public function test_scopes_intersect_with_existing_knowledge_permissions_and_hide_price(): void
    {
        $c = $this->thread();
        $e = ProductEnquiry::first();
        $s = app(SupportFaqService::class)->read();
        $r = app(EnquiryReceptionReply::class);
        $facts = $r->facts($e, $s, 'inspection');
        $this->assertStringContainsString('inspection', json_encode($facts));
        $this->assertStringNotContainsString('9000', json_encode($facts));
        $s['productCatalog']['isEnabled'] = false;
        $s['faq']['isEnabled'] = false;
        $this->assertSame([], $r->facts($e, $s, 'inspection'));
    }

    public function test_hallucinated_collection_values_and_invalid_citations_are_rejected(): void
    {
        $c = $this->thread();
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"answer":"Guaranteed delivery tomorrow","source_ids":["invented"],"fields":{"quantity":{"value":"100","evidence":"Need two"}},"has_unanswered_question":true}');
        app(EnquiryReceptionReply::class)->reply($c, $this->source($c, 'Need two'));
        $this->assertArrayNotHasKey('quantity', SupportEnquiryIntake::first()->fields);
        $this->assertStringNotContainsString('Guaranteed', $c->messages()->latest('id')->first()->content);
    }

    public function test_off_queues_existing_open_enquiry_without_calling_model_and_is_idempotent(): void
    {
        $c = $this->thread();
        $c->update(['status' => 'open']);
        $s = StoreSetting::where('key', 'support_faq')->first();
        $v = $s->value;
        $v['enquiryAccess']['isEnabled'] = false;
        $s->update(['value' => $v]);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        $m = $this->source($c, 'Hello');
        app(EnquiryReceptionReply::class)->reply($c, $m);
        app(EnquiryReceptionReply::class)->reply($c, $m);
        $this->assertSame('waiting_human', $c->fresh()->status);
        $this->assertSame(1, $c->notes()->count());
        $this->assertSame(0, $c->messages()->where('sender_role', 'ai')->count());
    }

    public function test_new_customer_input_during_generation_discards_old_output(): void
    {
        $c = $this->thread();
        $m = $this->source($c, 'Need two');
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturnUsing(function () use ($c) {
            $this->source($c, 'Actually need three');

            return '{"answer":"","source_ids":[],"fields":{"quantity":{"value":"two","evidence":"Need two"}}}';
        });
        app(EnquiryReceptionReply::class)->reply($c, $m);
        $this->assertSame(0, $c->messages()->where('sender_role', 'ai')->count());
    }

    public function test_required_questions_are_not_repeated_and_malformed_output_fails_closed(): void
    {
        $c = $this->thread();
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->times(3)->andReturn('{"answer":"","source_ids":[],"fields":{},"has_unanswered_question":false}', '{"answer":"","source_ids":[],"fields":{},"has_unanswered_question":false}', '{"answer":"Invented","source_ids":"bad"}');
        $r = app(EnquiryReceptionReply::class);
        $r->reply($c, $this->source($c, 'Hello'));
        $r->reply($c, $this->source($c, 'Thanks'));
        $r->reply($c, $this->source($c, 'Another question'));
        $messages = $c->messages()->where('sender_role', 'ai')->orderBy('id')->pluck('content');
        $this->assertStringContainsString('What quantity', $messages[0]);
        $this->assertStringNotContainsString('What quantity', $messages[1]);
        $this->assertStringContainsString('Where is the service', $messages[1]);
        $this->assertStringContainsString('cannot process', $messages[2]);
        $this->assertStringNotContainsString('Invented', $messages[2]);
    }

    public function test_product_detail_permission_is_required_for_product_faq_and_first_reply_uses_after_response(): void
    {
        $c = $this->thread();
        $s = app(SupportFaqService::class)->read();
        $s['faq']['isEnabled'] = false;
        $s['productCatalog']['canExplainSizingAndMaterials'] = false;
        $this->assertSame([], app(EnquiryReceptionReply::class)->facts(ProductEnquiry::first(), $s, 'inspection'));
        Bus::fake([ProcessEnquiryReception::class]);
        $p = Product::first();
        $this->post('/enquiries', ['product_id' => $p->id, 'request_key' => (string) Str::uuid(), 'name' => 'Local QA', 'email' => 'qa@example.test', 'requirements' => 'Need a container'])->assertRedirect();
        $e = ProductEnquiry::where('reference', '!=', 'TEST-REPLY')->firstOrFail();
        $this->post('/enquiries/'.$e->id.'/support')->assertRedirect();
        Bus::assertDispatchedAfterResponse(ProcessEnquiryReception::class, fn ($job) => $job->conversationId === $e->fresh()->support_conversation_id);
        $this->assertSame('open', $e->fresh()->supportConversation->status);
    }

    public function test_mixed_known_and_unknown_topics_preserve_both_and_queue_only_once(): void
    {
        $c = $this->thread();
        $c->update(['status' => 'open']);
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->twice()->andReturn('{"answer":"Delivery is subject to site access confirmation.","source_ids":["delivery"],"fields":{},"has_unanswered_question":true}');
        $r = app(EnquiryReceptionReply::class);
        $m = $this->source($c, 'Where do you deliver and can you guarantee Friday?');
        $r->reply($c, $m);
        $r->reply($c, $this->source($c, $m->content));
        $state = SupportEnquiryIntake::first();
        $this->assertCount(1, $state->unresolved);
        $this->assertCount(2,$state->answered);
        $this->assertSame('waiting_human',$c->fresh()->status);
        $this->assertSame(1,$c->notes()->where('content','like','[Control]%')->count());
        $this->assertStringContainsString('needs confirmation',$c->messages()->where('sender_role','ai')->latest('id')->first()->content);
    }
    public function test_waiting_enquiry_analyzes_attachment_but_requires_typed_intake_evidence(): void
    {
        $c=$this->thread();
        $setting=StoreSetting::where('key','support_faq')->first();$value=$setting->value;$value['attachmentAnalysis']=['image'=>true];$setting->update(['value'=>$value]);
        $m=$this->source($c,'Please explain this image');
        \Illuminate\Support\Facades\Storage::fake('support-private');
        \Illuminate\Support\Facades\Storage::disk('support-private')->put('test.png','bytes');
        $a=\App\Models\SupportAttachment::create(['id'=>(string)Str::uuid(),'conversation_id'=>$c->id,'message_id'=>$m->id,'owner_hash'=>$c->owner_hash,'kind'=>'image','name'=>'test.png','mime'=>'image/png','disk'=>'support-private','path'=>'test.png','sha256'=>hash('sha256','bytes'),'status'=>'ready']);
        $service=app(\App\Services\Support\SupportAttachmentAnalysisService::class);
        $this->assertTrue($service->schedule($m));
        $this->mock(\App\Services\Support\SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andReturn(['text'=>'Two containers, delivery Singapore','limitations'=>['Illustrative only'],'needsConfirmation'=>true,'provider'=>'openai','model'=>'vision']);
        $this->assertTrue($service->process($m));
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturnUsing(function($purpose,$prompt)use($a){
            $this->assertStringContainsString('customer_attachment_observations',$prompt);
            $this->assertStringContainsString('Two containers',$prompt);
            return json_encode(['answer'=>'Your image appears to show containers; please confirm the details.','source_ids'=>['attachment:'.$a->id],'fields'=>['quantity'=>['value'=>'Two','evidence'=>'Two containers']],'has_unanswered_question'=>false]);
        });
        app(EnquiryReceptionReply::class)->reply($c,$m);
        $this->assertArrayNotHasKey('quantity',SupportEnquiryIntake::first()->fields);
        $this->assertStringContainsString('appears',$c->messages()->where('sender_role','ai')->first()->content);
        $c->update(['status'=>'human_mode']);$this->assertFalse($service->schedule($m));
    }

    public function test_waiting_enquiry_attachment_reply_retries_lock_contention_before_takeover():void
    {
        $c=$this->thread();$m=$this->source($c,'Explain attachment');
        $ai=\Mockery::mock(\App\Services\Support\FaqAutoReplyService::class);
        $ai->shouldReceive('reply')->once()->andReturn(false);
        $job=\Mockery::mock(\App\Jobs\ReplyToAnalyzedSupportAttachment::class,[$m->id])->makePartial();
        $job->shouldReceive('release')->once()->with(5);
        $job->handle($ai);
        $this->assertSame('waiting_human',$c->fresh()->status);
    }

    public function test_unreadable_enquiry_attachment_has_honest_fallback_without_answer_model_call():void
    {
        $c=$this->thread();$m=$this->source($c,'Please read this');
        $setting=StoreSetting::where('key','support_faq')->first();$value=$setting->value;$value['attachmentAnalysis']=['image'=>true];$setting->update(['value'=>$value]);
        \Illuminate\Support\Facades\Storage::fake('support-private');\Illuminate\Support\Facades\Storage::disk('support-private')->put('bad.png','bytes');
        \App\Models\SupportAttachment::create(['id'=>(string)Str::uuid(),'conversation_id'=>$c->id,'message_id'=>$m->id,'owner_hash'=>$c->owner_hash,'kind'=>'image','name'=>'bad.png','mime'=>'image/png','disk'=>'support-private','path'=>'bad.png','sha256'=>hash('sha256','bytes'),'status'=>'ready']);
        $this->mock(\App\Services\Support\SupportAttachmentAnalyzer::class)->shouldReceive('analyze')->once()->andThrow(new \RuntimeException('Unreadable fixture'));
        app(\App\Services\Support\SupportAttachmentAnalysisService::class)->process($m);
        $this->mock(AiConnectionService::class)->shouldNotReceive('generateText');
        app(EnquiryReceptionReply::class)->reply($c,$m);
        $reply=$c->messages()->where('sender_role','ai')->first();$this->assertNotNull($reply);$this->assertStringContainsString('could not read',$reply->content);
    }

}
