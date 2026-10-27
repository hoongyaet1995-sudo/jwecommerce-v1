<?php
namespace Tests\Feature;

use App\Models\MarketingAiRun;
use App\Services\Marketing\MarketingPlanningConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingPlanningHandoffTest extends TestCase
{
    use RefreshDatabase, MarketingResearchFixtures;

    private function sample()
    {
        $p=$this->researchProject('Jasmine Green Tea');
        config(['marketing.billing.enabled'=>true,'marketing.conversation.enabled'=>true]);
        $this->post('/admin/marketing/workspace/'.$p->id.'/preferences',['request_id'=>(string)Str::uuid(),'version'=>$p->version,'language'=>'zh-MY','platforms'=>['YouTube'],'duration'=>'auto','subtitles'=>1,'narration'=>1,'voice'=>'ai','music'=>'none'])->assertRedirect();
        foreach(['duration_seconds'=>45,'goal'=>'introduction','audience'=>'喜欢清爽茶饮的成年人','market'=>'Malaysia','pain_point'=>'忙碌时需要放松','differentiator'=>'茶香与自然体验','tone'=>'清新自然','cta'=>'了解更多'] as $field=>$value){
            app(MarketingPlanningConfirmation::class)->answer($p->id,$p->owner_id,$p->fresh()->version,(string)Str::uuid(),$field,$value);
        }
        return $p->fresh();
    }

    private function reply():array
    {
        $concepts=array_map(fn($i)=>['title'=>'Tea direction '.$i,'approach'=>['use_scenario','feature_detail','question_answer'][$i-1],'hook'=>'Distinct opening '.$i,'story'=>'Original tea story with a distinct focus '.$i,'materials'=>'Tea leaves and a cup','duration'=>45,'ratio'=>'9:16','video_type'=>'presenter','visual_treatment'=>'Soft natural light with tea','recommended'=>$i===1,'reason'=>'Fits the confirmed brief','references'=>[]],range(1,3));
        return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['concepts'=>$concepts])]]]]];
    }

    public function test_confirmation_prepares_first_concepts_once_and_get_never_generates():void
    {
        $p=$this->sample();Http::fakeSequence()->push($this->reply());
        $body=['request_id'=>(string)Str::uuid(),'version'=>$p->version];$url='/admin/marketing/workspace/'.$p->id.'/confirm';
        $this->post($url,$body)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(3,$p->fresh()->concepts??[]);
        $this->assertSame(45,$p->fresh()->brief['duration_seconds']);
        $this->assertSame('清新自然',$p->fresh()->brief['tone']);
        $this->post($url,$body)->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=concepts')->assertOk()->assertSee('Tea direction 1');
        Http::assertSentCount(1);$this->assertSame(1,MarketingAiRun::count());
    }

    public function test_failure_keeps_confirmed_summary_and_replay_does_not_retry():void
    {
        $p=$this->sample();Http::fakeSequence()->push(['error'=>['message'=>'Fixture unavailable']],503);
        $body=['request_id'=>(string)Str::uuid(),'version'=>$p->version];$url='/admin/marketing/workspace/'.$p->id.'/confirm';
        $this->post($url,$body)->assertRedirect(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'concepts']))->assertSessionHasErrors('generation');
        $this->assertSame('confirmed',$p->fresh()->planning_state['status']);
        $this->assertSame('清新自然',$p->fresh()->brief['tone']);
        $this->post($url,$body)->assertRedirect()->assertSessionHasErrors('generation');
        Http::assertSentCount(1);$this->assertSame('failed',MarketingAiRun::firstOrFail()->status);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=concepts')->assertOk()->assertSee('生成3个创意')->assertSee('摘要已保留');
        Http::assertSentCount(1);
    }

    public function test_existing_concepts_survive_summary_confirmation_without_new_ai():void
    {
        $p=$this->sample();app(MarketingPlanningConfirmation::class)->confirm($p->id,$p->owner_id,$p->version,(string)Str::uuid());$p->refresh();$old=[['title'=>'Saved original direction']];$p->update(['concepts'=>$old]);
        $this->post('/admin/marketing/workspace/'.$p->id.'/confirm',['request_id'=>(string)Str::uuid(),'version'=>$p->version])->assertRedirect();
        $this->assertSame($old,$p->fresh()->concepts);Http::assertNothingSent();
    }
}
