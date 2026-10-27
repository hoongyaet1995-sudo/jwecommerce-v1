<?php
namespace Tests\Feature;
use App\Models\{MarketingProject,MarketingConversationMessage,User};
use App\Services\Marketing\{MarketingWorkflow,MarketingPlanningConfirmation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingGuidedPlanningTest extends TestCase
{
    use RefreshDatabase,MarketingResearchFixtures;
    private function sample():MarketingProject{
        $p=$this->researchProject('Jasmine Green Tea');config(['marketing.billing.enabled'=>true,'marketing.conversation.enabled'=>true]);
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences',['request_id'=>(string)Str::uuid(),'version'=>$p->version,'language'=>'zh-MY','platforms'=>['YouTube'],'duration'=>'auto','subtitles'=>1,'narration'=>1,'voice'=>'ai','music'=>'none'])->assertRedirect();
        return $p->fresh();
    }
    private function url(MarketingProject $p):string{return '/admin/marketing/workspace/'.$p->id.'/conversation';}
    private function body(MarketingProject $p,array $extra=[]):array{return $extra+['request_id'=>(string)Str::uuid(),'screen'=>'guided','version'=>$p->fresh()->version];}
    public function test_guided_page_shares_goal_and_tone_options_without_old_navigation_clutter():void{
        $p=$this->sample();$r=$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk();
        $r->assertSee('data-planning-choice',false)->assertSee('品牌曝光')->assertSee('预约')->assertSee('清新自然')->assertSee('大胆夸张')->assertSee('data-planning-confirmation',false)->assertDontSee('ws-assistant-hint',false)->assertDontSee('检查策划摘要')->assertDontSee('data-chat-reload',false);
        Http::assertNothingSent();
    }
    public function test_fixed_choice_updates_summary_and_next_question_once_without_ai():void{
        $p=$this->sample();$body=$this->body($p,['choice'=>['field'=>'goal','value'=>'awareness']]);
        $r=$this->postJson($this->url($p),$body)->assertOk()->assertJsonPath('guide.field','audience');
        $this->assertStringContainsString('品牌曝光',$r->json('summary_html'));$this->assertSame('confirmed',$p->fresh()->planning_state['fields']['goal']['status']);
        $version=$p->fresh()->version;$this->postJson($this->url($p),$body)->assertOk();$this->assertSame($version,$p->fresh()->version);$this->assertSame(2,MarketingConversationMessage::where('project_id',$p->id)->count());Http::assertNothingSent();
    }
    public function test_natural_reply_auto_syncs_draft_and_repeated_delivery_does_not_repeat_ai():void{
        $p=$this->sample();Http::fakeSequence()->push(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['topic_scope'=>'current','reply'=>'可以围绕日常品茶的轻松感展开。','changes'=>[['field'=>'audience','value'=>'喜欢日常品茶的年轻成年人'],['field'=>'tone','value'=>'清新自然']]])]]]]]);
        $body=$this->body($p,['message'=>'面向年轻成年人，清新自然一点']);$r=$this->postJson($this->url($p),$body)->assertOk();
        $this->assertStringContainsString('喜欢日常品茶的年轻成年人',$r->json('summary_html')??'');$this->assertSame('清新自然',$p->fresh()->planning_state['fields']['tone']['value']);$this->assertSame('suggested',$p->fresh()->planning_state['fields']['tone']['status']);$this->assertSame('draft',$p->fresh()->planning_state['status']);
        $this->assertSame('accepted',MarketingConversationMessage::where('project_id',$p->id)->where('role','assistant')->firstOrFail()->proposal_status);
        $version=$p->fresh()->version;$this->postJson($this->url($p),$body)->assertOk();$this->assertSame($version,$p->fresh()->version);Http::assertSentCount(1);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk()->assertSee('已同步摘要草稿')->assertDontSee('data-chat-decision="accept"',false);
    }
    public function test_stale_and_forged_choice_do_not_change_summary_or_call_ai():void{
        $p=$this->sample();$before=$p->planning_state;
        $this->postJson($this->url($p),$this->body($p,['version'=>0,'choice'=>['field'=>'goal','value'=>'purchase']]))->assertStatus(409);
        $this->postJson($this->url($p),$this->body($p,['choice'=>['field'=>'goal','value'=>'invented']]))->assertStatus(422);
        $this->assertSame($before,$p->fresh()->planning_state);Http::assertNothingSent();
    }
    public function test_late_ai_reply_does_not_overwrite_a_newer_manual_answer():void{
        $p=$this->sample();
        Http::fake(function()use($p){
            app(MarketingPlanningConfirmation::class)->answer($p->id,$p->owner_id,$p->fresh()->version,(string)Str::uuid(),'tone','高级质感');
            return Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['topic_scope'=>'current','reply'=>'建议清新自然。','changes'=>[['field'=>'tone','value'=>'清新自然']]])]]]]]);
        });
        $r=$this->postJson($this->url($p),$this->body($p,['message'=>'请建议调性']))->assertOk();
        $this->assertSame('高级质感',$p->fresh()->planning_state['fields']['tone']['value']);
        $m=MarketingConversationMessage::where('project_id',$p->id)->where('role','assistant')->firstOrFail();$this->assertSame('stale',$m->context['summary_sync']);$this->assertSame('pending',$m->proposal_status);Http::assertSentCount(1);
    }
    public function test_tone_direction_reaches_storyboard_semantic_inputs_and_custom_tone_is_preserved():void{
        $p=$this->sample();$p->update(['brief'=>array_replace($p->brief,['tone'=>'清新自然'])]);$seen=null;
        $this->app->instance('marketing.storyboard.semantic_transport',function($payload)use(&$seen){$seen=$payload;return ['scenes'=>[['seconds'=>5,'message'=>'茶香片刻','visual'=>'茶叶与柔和自然光','voiceover'=>'','subtitle'=>'','presenter_required'=>false,'fact_refs'=>[]]],'missing_facts'=>[]];});
        app(\App\Services\Marketing\MarketingStoryboardSemanticClient::class)->propose([],['scenes'=>[]],['project_id'=>$p->id,'text'=>'延续所选调性']);
        $this->assertSame('清新自然',$seen['context']['creative_tone']['tone']);$this->assertStringContainsString('柔和自然光',$seen['context']['creative_tone']['direction']);
        $p->update(['planning_state'=>array_replace_recursive($p->planning_state,['fields'=>['tone'=>['value'=>'用户原有慢节奏调性','status'=>'confirmed']]])]);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=guided')->assertOk()->assertSee('用户原有慢节奏调性（自定义）');Http::assertNothingSent();
    }
    public function test_pending_auto_duration_does_not_display_old_confirmed_seconds():void{
        $p=$this->sample();$p->update(['brief'=>array_replace($p->brief,['duration_seconds'=>15])]);
        $view=app(\App\Services\Marketing\MarketingWorkspaceView::class)->data($p->fresh(),'guided');
        $this->assertStringContainsString('时长待推荐',$view['summary']['平台与时长']);Http::assertNothingSent();
    }
}
