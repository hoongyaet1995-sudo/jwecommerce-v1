<?php
namespace Tests\Feature;
use App\Models\{StoreSetting,SupportConversation,SupportReplyBatch};
use App\Services\Support\SupportReplyCadenceService;
use Illuminate\Support\Facades\{Queue,Schema};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

class SupportReplyCadenceTest extends SupportChatApiTest
{
    protected function setUp():void {
        parent::setUp();
        (require database_path('migrations/2026_10_07_220000_create_support_reply_batches_table.php'))->up();
        Schema::table('support_messages',function(Blueprint $t){$t->unsignedBigInteger('ai_settings_version')->nullable();$t->unsignedBigInteger('ai_control_version')->nullable();$t->string('delivery_status')->nullable();});
        Queue::fake();$this->freezeTime();
    }
    private function cadenceConversation():SupportConversation {
        StoreSetting::create(['key'=>'support_faq','value'=>['version'=>1,'autoReplyEnabled'=>true,'replyCadence'=>['enabled'=>true,'collectionSeconds'=>2,'intervalSeconds'=>1,'maxSegments'=>3],'faq'=>['isEnabled'=>true,'items'=>[['id'=>'policy','question'=>'Policies','answer'=>'Delivery is RM8. Returns within 14 days with conditions.','isActive'=>true,'keywords'=>[]]]]]]);
        return SupportConversation::create(['id'=>(string)Str::uuid(),'owner_hash'=>hash('sha256',Str::uuid()),'channel'=>'web_guest','status'=>'open']);
    }
    private function message(SupportConversation $c,string $text) {return $c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>$text]);}
    public function test_boundary_redirect_uses_normal_cadence_publication_once():void{
        $c=$this->cadenceConversation();$service=app(SupportReplyCadenceService::class);$source=$this->message($c,'Is the moon rounder overseas?');$service->enqueue($source);$batch=SupportReplyBatch::first();
        $knowledge=$this->mock(\App\Services\Support\UnifiedKnowledgeService::class);$knowledge->shouldReceive('redact')->andReturnUsing(fn($q)=>$q);$knowledge->shouldReceive('retrieve')->once()->andReturn([['id'=>'support:boundary','answer'=>'off_topic']]);
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');$this->travel(3)->seconds();$service->process($batch->id);
        $this->assertSame('ready',$batch->fresh()->status);$this->assertSame(0,$c->messages()->where('sender_role','ai')->count());$service->publish($batch->id);$service->publish($batch->id);$this->assertSame(1,$c->messages()->where('sender_role','ai')->count());$this->assertSame('off_topic',$c->messages()->where('sender_role','ai')->first()->ai_trace['decision']);
    }
    public function test_collection_deadline_is_bounded_and_stale_or_duplicate_jobs_do_not_reset_it():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);
        $first=$this->message($c,'Deliver to Penang');$this->assertTrue($s->enqueue($first));
        $batch=SupportReplyBatch::first();$deadline=$batch->deadline_at->toIso8601String();
        $this->travel(1)->seconds();$next=$this->message($c,'What is shipping?');$s->enqueue($next);
        $s->enqueue($first);$s->enqueue($next);
        $this->assertSame(1,SupportReplyBatch::count());$this->assertSame($next->id,$batch->fresh()->latest_source_id);
        $this->assertSame($deadline,$batch->fresh()->deadline_at->toIso8601String());
        $this->travel(3)->seconds();$last=$this->message($c,'Return window?');$s->enqueue($last);
        $this->assertTrue($batch->fresh()->due_at->equalTo($batch->fresh()->deadline_at));
    }
    public function test_generation_covers_collected_inputs_once_and_only_publishes_due_segments():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);
        $s->enqueue($this->message($c,'Deliver to Penang'));
        $this->travel(1)->seconds();$s->enqueue($this->message($c,'What is shipping?'));
        $s->enqueue($this->message($c,'Return window?'));
        $batch=SupportReplyBatch::first();
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($f,$p)=>str_contains($p,'Deliver to Penang')&&str_contains($p,'What is shipping?')&&str_contains($p,'Return window?'))
            ->andReturn('{"answer":"Delivery costs RM8, within Peninsular Malaysia.\n\nReturns within 14 days, unused in original packaging.","faq_ids":["policy"],"segment_paragraphs":true}');
        $s->process($batch->id);$this->assertSame(0,$c->messages()->where('sender_role','ai')->count());
        $this->travel(2)->seconds();$s->process($batch->id);$s->process($batch->id);
        $this->assertSame('ready',$batch->fresh()->status);$this->assertSame(0,$c->messages()->where('sender_role','ai')->count());
        $s->publish($batch->id);$s->publish($batch->id);
        $this->assertSame(1,$c->messages()->where('sender_role','ai')->count());
        $this->travel(1)->seconds();$s->publish($batch->id);$s->publish($batch->id);
        $this->assertSame(2,$c->messages()->where('sender_role','ai')->count());$this->assertSame('completed',$batch->fresh()->status);
        $this->assertStringNotContainsString('Delivery costs',\Illuminate\Support\Facades\DB::table('support_reply_batches')->value('segments'));
    }
    public function test_takeover_after_first_segment_cancels_unsent_messages():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);$source=$this->message($c,'Help');$s->enqueue($source);$batch=SupportReplyBatch::first();
        $batch->update(['status'=>'generating']);
        $s->prepare($batch->id,$c,$source,"One\n\nTwo",[],['decision'=>'grounded','candidateSegments'=>['One','Two']],app(\App\Services\SupportFaqService::class)->read());
        $s->publish($batch->id);$c->update(['status'=>'human_mode']);$this->travel(1)->seconds();$s->publish($batch->id);
        $this->assertSame(1,$c->messages()->where('sender_role','ai')->count());$this->assertSame('cancelled',$batch->fresh()->status);
    }
    public function test_new_input_cancels_ready_batch_and_keeps_new_request_separate():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);$source=$this->message($c,'Penang');$s->enqueue($source);$batch=SupportReplyBatch::first();
        $batch->update(['status'=>'generating']);
        $s->prepare($batch->id,$c,$source,"Penang answer\n\nConditions",[],['decision'=>'grounded','candidateSegments'=>['Penang answer','Conditions']],app(\App\Services\SupportFaqService::class)->read());
        $s->publish($batch->id);$s->enqueue($this->message($c,'Actually Sabah'));$s->publish($batch->id);
        $this->assertSame('cancelled',$batch->fresh()->status);$this->assertSame(1,$c->messages()->where('sender_role','ai')->count());
        $this->assertSame(1,SupportReplyBatch::where('status','collecting')->count());
    }
    public function test_each_control_boundary_blocks_remaining_segments():void {
        foreach(['resolved','archive','staff_reply','channel_pause','settings_change'] as $boundary){
            StoreSetting::where('key','support_faq')->delete();$c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);$source=$this->message($c,'Help');$s->enqueue($source);$batch=SupportReplyBatch::where('conversation_id',$c->id)->first();$batch->update(['status'=>'generating']);
            $settings=app(\App\Services\SupportFaqService::class)->read();$s->prepare($batch->id,$c,$source,"One\n\nTwo",[],['decision'=>'grounded','candidateSegments'=>['One','Two']],$settings);$s->publish($batch->id);
            if($boundary==='resolved')$c->update(['status'=>'resolved']);
            elseif($boundary==='archive')$c->notes()->create(['content'=>'[Archive] true']);
            elseif($boundary==='staff_reply')$c->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'human_agent','content'=>'Staff is here']);
            else{if($boundary==='master_pause')$settings['autoReplyEnabled']=false;if($boundary==='channel_pause')$settings['aiChannels']['storefront']=false;if($boundary==='settings_change')$settings['version']++;StoreSetting::where('key','support_faq')->update(['value'=>$settings]);}
            $this->travel(1)->seconds();$s->publish($batch->id);
            $this->assertSame(1,$c->messages()->where('sender_role','ai')->count(),$boundary);$this->assertSame('cancelled',$batch->fresh()->status,$boundary);
        }
    }
    public function test_unconfirmed_provider_send_is_pending_before_reservation_and_stops_batch_without_replay():void {
        $c=$this->cadenceConversation();$c->update(['channel'=>'whatsapp']);$s=app(SupportReplyCadenceService::class);$source=$this->message($c,'Help');$s->enqueue($source);$batch=SupportReplyBatch::first();$batch->update(['status'=>'generating']);
        $s->prepare($batch->id,$c,$source,"One\n\nTwo",[],['decision'=>'grounded','candidateSegments'=>['One','Two']],app(\App\Services\SupportFaqService::class)->read());
        $this->mock(\App\Services\Social\WhatsAppReplyService::class)->shouldReceive('sendGenerated')->once()->andReturnUsing(function($message){
            $this->assertSame('pending',$message->delivery_status);$message->update(['delivery_status'=>'unknown']);
        });
        $s->publish($batch->id);$this->travel(2)->seconds();$s->publish($batch->id);
        $this->assertSame('unknown',$batch->fresh()->status);$this->assertSame(1,$c->messages()->where('sender_role','ai')->count());
    }
    public function test_whatsapp_provider_acceptance_advances_remaining_segments_without_receipt():void {
        $c=$this->cadenceConversation();$c->update(['channel'=>'whatsapp']);$s=app(SupportReplyCadenceService::class);$source=$this->message($c,'Help');$s->enqueue($source);$batch=SupportReplyBatch::first();$batch->update(['status'=>'generating']);
        $s->prepare($batch->id,$c,$source,"One\n\nTwo",[],['decision'=>'grounded','candidateSegments'=>['One','Two']],app(\App\Services\SupportFaqService::class)->read());
        $this->mock(\App\Services\Social\WhatsAppReplyService::class)->shouldReceive('sendGenerated')->twice()->andReturnUsing(fn($message)=>$message->update(['delivery_status'=>'accepted']));
        $s->publish($batch->id);$this->assertSame('ready',$batch->fresh()->status);
        $this->travel(1)->seconds();$s->publish($batch->id);
        $this->assertSame('completed',$batch->fresh()->status);$this->assertSame(2,$c->messages()->where('sender_role','ai')->count());
    }
    public function test_batch_state_is_staff_only_and_failed_generation_is_visible():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);$s->enqueue($this->message($c,'Help'));$batch=SupportReplyBatch::first();
        $batch->update(['status'=>'generating','generation_started_at'=>now()->subMinutes(3)]);$s->process($batch->id);
        $this->assertSame('failed',$batch->fresh()->status);
        $chat=app(\App\Services\Support\SupportChatService::class);
        $this->assertArrayNotHasKey('replyCadence',$chat->publicView($c));
        $this->assertSame('generation_interrupted',$chat->adminView($c)['replyCadence']['stopReason']);
    }
    public function test_storefront_route_queues_enabled_cadence_without_generating_inline():void {
        $c=$this->cadenceConversation();
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldNotReceive('generateText');
        $r=$this->postJson('/support/chat/messages',['client_message_id'=>(string)Str::uuid(),'content'=>'Shipping to Penang?'])->assertOk();
        $this->assertCount(1,$r->json('conversation.messages'));
        Queue::assertPushed(\App\Jobs\ProcessSupportReplyBatch::class);
    }
    public function test_new_input_preserves_unanswered_generation_sources():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);
        $first=$this->message($c,'What is shipping?');$s->enqueue($first);
        $old=SupportReplyBatch::first();$old->update(['status'=>'generating']);
        $this->travel(6)->seconds();$last=$this->message($c,'And returns?');$s->enqueue($last);
        $replacement=SupportReplyBatch::where('status','collecting')->first();
        $this->assertSame($first->id,$replacement->first_source_id);
        $this->assertSame($last->id,$replacement->latest_source_id);
        $this->assertSame('cancelled',$old->fresh()->status);
    }

    public function test_input_cancelled_predecessor_is_recovered_before_enqueue():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);
        $first=$this->message($c,'Shipping?');$s->enqueue($first);$old=SupportReplyBatch::first();
        $last=$this->message($c,'Returns?');$s->process($old->id);
        $this->assertSame('cancelled',$old->fresh()->status);
        $s->enqueue($last);$new=SupportReplyBatch::where('status','collecting')->first();
        $this->assertSame($first->id,$new->first_source_id);
        $chat=app(\App\Services\Support\SupportChatService::class);$chat->primeCadence([$c->id]);
        $this->assertSame($new->id,$chat->adminView($c)['replyCadence']['id']);
    }

    public function test_action_url_overflow_fails_before_any_segment_is_published():void {
        app()->instance(\App\Services\Support\SupportActionResolver::class,new \App\Services\Support\SupportActionResolver);
        $c=$this->cadenceConversation();$c->update(['channel'=>'whatsapp']);$s=app(SupportReplyCadenceService::class);
        $source=$this->message($c,'Help');$s->enqueue($source);$b=SupportReplyBatch::first();$b->update(['status'=>'generating']);
        $answer='First'."\n\n".str_repeat('x',1980);
        $s->prepare($b->id,$c,$source,$answer,[['type'=>'view_order_tracking']],['decision'=>'grounded','candidateSegments'=>['First',str_repeat('x',1980)]],app(\App\Services\SupportFaqService::class)->read());
        $this->assertSame('failed',$b->fresh()->status);$this->assertSame('answer_exceeds_channel_limit',$b->fresh()->stop_reason);
        $this->assertSame(0,$c->messages()->where('sender_role','ai')->count());
    }

    public function test_completed_replacement_prevents_old_cancelled_input_replay():void {
        $c=$this->cadenceConversation();$s=app(SupportReplyCadenceService::class);
        $a=$this->message($c,'Shipping?');$s->enqueue($a);$old=SupportReplyBatch::first();$old->update(['status'=>'generating']);
        $b=$this->message($c,'Returns?');$s->enqueue($b);$replacement=SupportReplyBatch::where('status','collecting')->first();$replacement->update(['status'=>'completed','next_index'=>1]);
        $last=$this->message($c,'Assembly?');$s->enqueue($last);
        $new=SupportReplyBatch::where('status','collecting')->first();$this->assertSame($last->id,$new->first_source_id);
    }

}
