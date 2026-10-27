<?php
namespace Tests\Feature;

use App\Models\{MarketingAiRun,MarketingRevision,MarketingStoryboardWorkspace};
use App\Services\Marketing\MarketingPlanningConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingConceptHandoffTest extends TestCase
{
    use RefreshDatabase,MarketingResearchFixtures;
    private function sample()
    {
        $p=$this->researchProject('Jasmine Green Tea');
        config(['marketing.billing.enabled'=>true,'marketing.conversation.enabled'=>true,'marketing.conversation.open_testing'=>false]);
        $concepts=array_map(fn($i)=>['title'=>'Tea story '.$i,'video_type'=>'product_showcase','story'=>'Explore tea in natural light '.$i,'hook'=>'Tea opening '.$i,'visual_treatment'=>'Tea leaves and a cup','duration'=>15,'ratio'=>'9:16'],range(1,3));
        $p->update(['planning_state'=>['status'=>'confirmed'],'preferences'=>['complete'=>true,'video_type'=>'product_showcase','narration'=>true,'subtitles'=>true],'concepts'=>$concepts,'brief'=>array_replace($p->brief,['duration_seconds'=>15,'tone'=>'清新自然','cta'=>'预约咨询'])]);
        return $p->fresh();
    }
    private function draft():array{return ['summary'=>'Tea in natural light','duration'=>15,'cta'=>'预约咨询','changes'=>[],'requirements'=>[],'scenes'=>[['visual'=>'Tea leaves and a cup under natural light','voiceover'=>'先看用途，欢迎询价。','subtitle'=>'欢迎询价','duration'=>15,'material'=>'Planned future tea image','asset_ids'=>[]]]];}
    private function response(array $data):array{return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]];}
    private function body($p):array{return ['request_id'=>(string)Str::uuid(),'version'=>$p->fresh()->version,'action'=>'select','index'=>2];}
    private function url($p):string{return '/admin/marketing/workspace/'.$p->id.'/creative';}

    public function test_select_prepares_narrative_and_director_once_without_media_and_refresh_is_free():void
    {
        $p=$this->sample();Http::fakeSequence()->push($this->response($this->draft()))->push($this->response(MarketingDirectorContractTest::directorOutput()));
        $body=$this->body($p);$this->post($this->url($p),$body)->assertRedirect()->assertSessionHasNoErrors();
        $p->refresh();$this->assertSame(2,$p->selected_concept);$this->assertNotNull($p->currentRevision->document['director_plan']??null);
        $w=MarketingStoryboardWorkspace::where('project_id',$p->id)->firstOrFail();$this->assertCount(1,$w->scene_order);
        $this->post($this->url($p),$body)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($this->url($p),$this->body($p))->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertDontSee('整理已有故事，进入分镜工作区');
        Http::assertSentCount(2);$this->assertSame(2,MarketingAiRun::count());$this->assertSame(2,MarketingRevision::count());
        $this->assertSame(0,\App\Models\MarketingStoryboardTask::count());$this->assertSame(0,\App\Models\MarketingStoryboardProviderCall::count());
    }
    public function test_failed_director_retains_narrative_replay_does_not_retry_and_explicit_recovery_reuses_it():void
    {
        $p=$this->sample();Http::fakeSequence()->push($this->response($this->draft()))->push(['error'=>['message'=>'fixture failure']],503)->push($this->response(MarketingDirectorContractTest::directorOutput()));
        $body=$this->body($p);$this->post($this->url($p),$body)->assertRedirect()->assertSessionHasErrors('generation');
        $saved=$p->fresh()->current_revision_id;$this->assertNotNull($saved);Http::assertSentCount(2);
        $this->post($this->url($p),$body)->assertRedirect()->assertSessionHasErrors('generation');Http::assertSentCount(2);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('重新准备导演稿与分镜')->assertDontSee('整理已有故事，进入分镜工作区');Http::assertSentCount(2);
        $this->post($this->url($p),$this->body($p))->assertRedirect()->assertSessionHasNoErrors();
        Http::assertSentCount(3);$this->assertSame($saved,$p->fresh()->currentRevision->parent_id);$this->assertSame(1,MarketingStoryboardWorkspace::count());
    }
    public function test_changed_duplicate_and_existing_workspace_are_not_replaced():void
    {
        $p=$this->sample();Http::fakeSequence()->push($this->response($this->draft()))->push($this->response(MarketingDirectorContractTest::directorOutput()));
        $body=$this->body($p);$this->post($this->url($p),$body)->assertSessionHasNoErrors();
        $before=MarketingStoryboardWorkspace::firstOrFail()->toArray();$revision=$p->fresh()->current_revision_id;
        $this->post($this->url($p),array_replace($body,['index'=>0]))->assertStatus(409);
        $this->post($this->url($p),array_replace($this->body($p),['index'=>0]))->assertStatus(409);
        $this->assertSame($before,MarketingStoryboardWorkspace::firstOrFail()->toArray());$this->assertSame($revision,$p->fresh()->current_revision_id);Http::assertSentCount(2);
    }
    public function test_previously_selected_empty_project_shows_explicit_preparation_without_get_ai():void
    {
        $p=$this->sample();$p->update(['selected_concept'=>2]);
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('准备导演稿与分镜')->assertDontSee('整理已有故事，进入分镜工作区');
        Http::assertNothingSent();
    }

    public function test_failed_narrative_never_retries_on_replay_or_refresh():void
    {
        $p=$this->sample();Http::fakeSequence()->push(['error'=>['message'=>'fixture unavailable']],503);
        $body=$this->body($p);$this->post($this->url($p),$body)->assertSessionHasErrors('generation');
        $this->post($this->url($p),$body)->assertSessionHasErrors('generation');
        $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk();
        $this->assertSame(2,$p->fresh()->selected_concept);$this->assertNull($p->fresh()->current_revision_id);Http::assertSentCount(1);
    }

    public function test_included_planning_limit_stops_director_without_media_or_retry():void
    {
        $p=$this->sample();config(['marketing.billing.included_daily_microusd'=>20000]);
        Http::fakeSequence()->push($this->response($this->draft()));
        $this->post($this->url($p),$this->body($p))->assertStatus(429);
        $this->assertNotNull($p->fresh()->current_revision_id);$this->assertSame(0,MarketingStoryboardWorkspace::count());
        Http::assertSentCount(1);$this->assertSame(1,MarketingAiRun::count());
    }

    public function test_each_imported_director_uses_its_own_scene_clock():void
    {
        $p=$this->sample();$draft=$this->draft();$draft['scenes'][0]['duration']=7;$draft['scenes'][]=array_replace($draft['scenes'][0],['duration'=>8,'visual'=>'Tea cup close up']);
        $director=MarketingDirectorContractTest::directorOutput();$template=$director['shots'][0];
        $director['shots']=[array_replace($template,['end'=>7,'beats'=>[['start'=>0,'end'=>7,'action'=>'茶叶自然舒展']]]),array_replace($template,['scene_number'=>2,'start'=>7,'end'=>15,'beats'=>[['start'=>7,'end'=>15,'action'=>'端起茶杯']]])];
        Http::fakeSequence()->push($this->response($draft))->push($this->response($director));
        $this->post($this->url($p),$this->body($p))->assertSessionHasNoErrors();
        $snapshot=app(\App\Services\Marketing\MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);
        $this->assertSame(15,$snapshot['timing']['content_seconds']);$this->assertCount(2,$snapshot['scenes']);
        $this->assertSame([['start'=>0,'end'=>8,'action'=>'端起茶杯']],$snapshot['scenes'][1]['director']['beats']);
        $this->assertSame(7,$snapshot['scenes'][1]['source_context']['director']['start']);Http::assertSentCount(2);
    }
}
