<?php
namespace Tests\Feature;

use App\Models\{MarketingRevision,MarketingScene,MarketingSceneRevision,MarketingStoryboardWorkspace as Workspace};
use App\Services\Marketing\{MarketingStoryboardWorkspace,MarketingStoryboardUpload,MarketingStoryboardDecisions,MarketingStoryboardClosure,MarketingMaterialVisionClient};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardClosureTest extends TestCase
{
    use MarketingResearchFixtures;
    protected function setUp():void {parent::setUp();$this->artisan('migrate:fresh')->run();}
    private function sample(string $continuity='pass'):array
    {
        $p=$this->researchProject();
        $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>[['visual'=>'人物胸口以上中近景，保持奶油西装与仓库背景。','duration'=>5,'voiceover'=>'','subtitle'=>'']]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        $ref=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('candidate.png'),true);
        $r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;
        $c['director']['framing']='胸口以上中近景';$c['frames']['start_frame']['instruction']='人物胸口以上中近景，保持奶油西装与仓库背景。';
        $checks=array_fill_keys(MarketingMaterialVisionClient::CHECKS,'pass');$checks['composition']=$continuity==='pass'?'fail':'pass';$checks['scene_continuity']=$continuity;
        $c['frames']['start_frame']['candidate_asset']=$ref;$c['frames']['start_frame']['qa']=['status'=>'uncertain','evidence'=>['checks'=>$checks,'summary'=>'人物商品均符合，仅缺少配对参考，未核对跨场景连续性。']];
        $this->revise($r,$c);
        return [$p,app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id),$ref];
    }
    private function revise($r,array $c):void {$n=MarketingSceneRevision::create(['scene_id'=>$r->scene_id,'parent_id'=>$r->id,'content'=>$c,'signature'=>$r->signature]);MarketingScene::find($r->scene_id)->update(['current_revision_id'=>$n->id]);}
    public function test_unassessed_continuity_requires_explicit_ack_and_preserves_original_qa():void
    {
        [$p,$s,$ref]=$this->sample('uncertain');$scene=$s['scenes'][0];$cmd=['scene_id'=>$scene['scene_id'],'revision_id'=>$scene['revision_id'],'role'=>'start_frame','sha256'=>$ref['sha256'],'sync_framing'=>false];
        $url=route('marketing.workspace.storyboard.write',[$p->id,'adopt-candidate']);
        $this->postJson($url,['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>$cmd])->assertStatus(422);
        $out=$this->postJson($url,['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>$cmd+['accept_unassessed_continuity'=>true]])->assertOk()->json('snapshot');
        $this->assertSame('uncertain',$out['scenes'][0]['frames']['start_frame']['qa']['evidence']['checks']['scene_continuity']);$this->assertTrue($out['readiness']);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_framing_sync_removes_conflicting_old_crop_without_changing_action_or_copy():void
    {
        [$p,$s,$ref]=$this->sample();$c=$s['scenes'][0];
        $out=app(MarketingStoryboardDecisions::class)->adopt($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['scene_id'=>$c['scene_id'],'revision_id'=>$c['revision_id'],'role'=>'start_frame','sha256'=>$ref['sha256'],'sync_framing'=>true]);
        $new=$out['scenes'][0];$this->assertStringNotContainsString('胸口以上',$new['frames']['start_frame']['instruction']);$this->assertStringNotContainsString('中近景',$new['visual']);
        $this->assertStringContainsString('奶油西装',$new['visual']);$this->assertSame($c['director']['motion'],$new['director']['motion']);$this->assertSame($c['voiceover'],$new['voiceover']);
    }
    public function test_timing_extension_preview_and_apply_preserve_images_and_voice():void
    {
        [$p,$s]=$this->sample();$r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;$c['voiceover']=str_repeat('讲',24);$this->revise($r,$c);
        $svc=app(MarketingStoryboardClosure::class);$plan=$svc->timingPreview($p->id,$p->owner_id,$s['story_version']);$this->assertSame(6,$plan['scenes'][0]['suggested_seconds']);
        $out=$svc->extendTiming($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$plan['key']);$next=$out['scenes'][0];
        $this->assertSame(6,$next['seconds']);$this->assertSame($c['voiceover'],$next['voiceover']);$this->assertSame($c['frames']['start_frame']['candidate_asset']['sha256'],$next['frames']['start_frame']['candidate_asset']['sha256']);
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_missing_frame_quote_is_read_only_and_does_not_include_candidates():void
    {
        [$p,$s]=$this->sample();$r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;$c['frames']['end_frame']['required']=true;$this->revise($r,$c);
        $q=app(MarketingStoryboardClosure::class)->missingPreview($p->id,$p->owner_id,$s['story_version']);
        $this->assertCount(1,$q['frames']);$this->assertSame('end_frame',$q['frames'][0]['role']);$this->assertNotEmpty($q['blockers']);
        $this->assertSame($s['story_version'],Workspace::where('project_id',$p->id)->first()->version);Http::assertNothingSent();Queue::assertNothingPushed();
    }

    private function rates($p):void
    {
        \App\Models\AiConnection::where('provider','openai')->update(['models'=>json_encode([['id'=>'fixture-model']])]);
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'FAKE CLOSURE','project_id'=>$p->id,'owner_id'=>$p->owner_id,'expires_at'=>now()->addHour()->toIso8601String(),'roles'=>['semantic','image','qa'],'max_total_microusd'=>10000,'max_calls'=>10]]);
        foreach(['semantic','image','qa'] as $r)config(['marketing.storyboard.roles.'.$r=>['model'=>'fixture-model','version'=>'1','cap_microusd'=>200,'pricing_verified_at'=>now()->toIso8601String(),'input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2]]);
        foreach([\App\Services\Marketing\MarketingMaterialImageClient::class,MarketingMaterialVisionClient::class] as $client)$this->mock($client,fn($m)=>$m->shouldReceive('configuration')->andReturn(['model'=>'fixture-model','connection_version'=>1]));
    }
    public function test_failed_continuity_is_not_treated_as_unassessed_even_with_ack():void
    {
        [$p,$s,$ref]=$this->sample('fail');$c=$s['scenes'][0];
        $this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'adopt-candidate']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>['scene_id'=>$c['scene_id'],'revision_id'=>$c['revision_id'],'role'=>'start_frame','sha256'=>$ref['sha256'],'sync_framing'=>false,'accept_unassessed_continuity'=>true]])->assertStatus(422);
        Http::assertNothingSent();
    }
    public function test_missing_only_confirm_queues_once_and_no_repairs_then_handoff_ready():void
    {
        [$p,$s,$ref]=$this->sample();$c=$s['scenes'][0];
        $s=app(MarketingStoryboardDecisions::class)->adopt($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['scene_id'=>$c['scene_id'],'revision_id'=>$c['revision_id'],'role'=>'start_frame','sha256'=>$ref['sha256'],'sync_framing'=>true]);
        $r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;$c['frames']['end_frame']['required']=true;$this->revise($r,$c);
        $this->rates($p);$svc=app(MarketingStoryboardClosure::class);$q=$svc->missingPreview($p->id,$p->owner_id,$s['story_version']);
        $this->assertSame([],$q['blockers']);$this->assertSame(400,$q['cap_microusd']);$this->assertSame(2,$q['max_posts']);
        $calls=0;app()->instance('marketing.storyboard.image_transport',function($compiled)use(&$calls,$ref){$calls++;return ['asset'=>$ref,'actual_microusd'=>10];});
        app()->instance('marketing.storyboard.qa_transport',fn()=>['status'=>'pass','evidence'=>['fixture'=>'offline']]);
        $request=(string)Str::uuid();$url=route('marketing.workspace.storyboard.write',[$p->id,'missing-images-prepare']);$body=['request_id'=>$request,'version'=>$s['story_version'],'preview_key'=>$q['key'],'consent'=>true];
        $out=$this->postJson($url,$body)->assertOk()->json();$task=\App\Models\MarketingStoryboardTask::find($out['preparation']['tasks'][0]);
        $this->assertSame(0,$task->result['request_policy']['max_repairs']);
        $this->assertSame(['end_frame'],MarketingSceneRevision::find($task->revision_id)->content['generation_roles']);
        $this->postJson($url,$body)->assertOk();Queue::assertPushed(\App\Jobs\ProcessMarketingStoryboardPreparation::class,1);
        app(\App\Services\Marketing\MarketingStoryboardPreparation::class)->process($task->id);
        $after=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);$this->assertTrue($after['readiness']);$this->assertSame(1,$calls);$this->assertSame($ref['sha256'],$after['scenes'][0]['frames']['start_frame']['asset']['sha256']);Http::assertNothingSent();
    }
    public function test_shortening_preview_cached_once_and_only_explicit_apply_changes_copy():void
    {
        [$p,$s]=$this->sample();$r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;$c['voiceover']=str_repeat('讲',24);$this->revise($r,$c);$this->rates($p);
        $answer=['scenes'=>[['scene_id'=>$s['scenes'][0]['scene_id'],'voiceover'=>'先看空间，再选用途。','subtitle'=>'先看空间，再选用途。','reason'=>'缩短到五秒内']]];
        Http::fake(['https://api.openai.com/v1/responses'=>Http::response(['id'=>'fake-shortening','status'=>'completed','usage'=>['input_tokens'=>10,'output_tokens'=>10],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($answer)]]]]])]);
        $svc=app(MarketingStoryboardClosure::class);$a=$svc->shortenPreview($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$svc->timingPreview($p->id,$p->owner_id,$s['story_version'])['key']);$b=$svc->shortenPreview($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$svc->timingPreview($p->id,$p->owner_id,$s['story_version'])['key']);$this->assertSame($a,$b);Http::assertSentCount(1);
        $before=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);$this->assertSame($c['voiceover'],$before['scenes'][0]['voiceover']);
        $out=$svc->applyShortening($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$a['key']);$this->assertSame(5,$out['scenes'][0]['seconds']);$this->assertSame($answer['scenes'][0]['voiceover'],$out['scenes'][0]['voiceover']);$this->assertSame($c['director']['motion'],$out['scenes'][0]['director']['motion']);$this->assertSame($c['frames']['start_frame']['candidate_asset']['sha256'],$out['scenes'][0]['frames']['start_frame']['candidate_asset']['sha256']);$this->assertFalse($out['timing']['requires_attention']);Queue::assertNothingPushed();
    }
    public function test_quote_rate_change_is_rejected_before_any_dispatch():void
    {
        [$p,$s]=$this->sample();$r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;unset($c['frames']['start_frame']['candidate_asset']);$this->revise($r,$c);$this->rates($p);
        $svc=app(MarketingStoryboardClosure::class);$q=$svc->missingPreview($p->id,$p->owner_id,$s['story_version']);config(['marketing.storyboard.roles.image.cap_microusd'=>300]);
        $this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'missing-images-prepare']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'preview_key'=>$q['key'],'consent'=>true])->assertStatus(409);Http::assertNothingSent();Queue::assertNothingPushed();
        $this->assertSame($s['story_version'],Workspace::where('project_id',$p->id)->first()->version);
    }
    public function test_shortening_rejects_displayed_model_price_drift_before_http():void
    {
        [$p,$s]=$this->sample();$r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;$c['voiceover']=str_repeat('讲',24);$this->revise($r,$c);$this->rates($p);$q=app(MarketingStoryboardClosure::class)->timingPreview($p->id,$p->owner_id,$s['story_version']);
        config(['marketing.storyboard.roles.semantic.cap_microusd'=>300]);
        $this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'timing-shorten-preview']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'preview_key'=>$q['key']])->assertStatus(409);Http::assertNothingSent();
    }
    public function test_queued_quote_rejects_rate_drift_before_image_transport():void
    {
        [$p,$s]=$this->sample();$r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;unset($c['frames']['start_frame']['candidate_asset']);$this->revise($r,$c);$this->rates($p);
        $svc=app(MarketingStoryboardClosure::class);$q=$svc->missingPreview($p->id,$p->owner_id,$s['story_version']);$called=0;
        app()->instance('marketing.storyboard.image_transport',function()use(&$called){$called++;throw new \RuntimeException('transport must not run');});
        $out=$svc->prepareMissing($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$q['key'],true);
        config(['marketing.storyboard.roles.image.cap_microusd'=>300]);
        try{app(\App\Services\Marketing\MarketingStoryboardPreparation::class)->process($out['preparation']['tasks'][0]);}catch(\Throwable){}
        $this->assertSame(0,$called);$this->assertSame('blocked',\App\Models\MarketingStoryboardTask::find($out['preparation']['tasks'][0])->status);Http::assertNothingSent();
    }
    public function test_previously_adopted_framing_can_be_resynchronized_without_regeneration():void
    {
        [$p,$s,$ref]=$this->sample();$r=MarketingSceneRevision::find($s['scenes'][0]['revision_id']);$c=$r->content;
        $c['frames']['start_frame']['asset']=$ref;unset($c['frames']['start_frame']['candidate_asset']);$c['frames']['start_frame']['readiness']='employee_declared_local_validated';$c['frames']['start_frame']['employee_acceptance']=['sha256'=>$ref['sha256'],'sync_framing'=>true];
        $c['director']['beats']=[['start'=>0,'end'=>5,'action'=>'胸口以上，抬手指向货柜']];$this->revise($r,$c);$s=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);$scene=$s['scenes'][0];$this->assertTrue($scene['frames']['start_frame']['accepted_framing_needs_sync']);
        $out=$this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'sync-accepted-framing']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>['scene_id'=>$scene['scene_id'],'revision_id'=>$scene['revision_id'],'role'=>'start_frame','sha256'=>$ref['sha256']]])->assertOk()->json('snapshot');
        $this->assertFalse($out['scenes'][0]['frames']['start_frame']['accepted_framing_needs_sync']);$this->assertSame(5,$out['scenes'][0]['director']['beats'][0]['end']);$this->assertStringContainsString('抬手指向货柜',$out['scenes'][0]['director']['beats'][0]['action']);$this->assertStringNotContainsString('胸口以上',$out['scenes'][0]['director']['beats'][0]['action']);$this->assertSame($ref['sha256'],$out['scenes'][0]['asset']['sha256']);$this->assertSame($c['frames']['start_frame']['qa'],$out['scenes'][0]['frames']['start_frame']['qa']);Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_missing_confirm_requires_consent_and_stale_timing_preview_cannot_write():void
    {
        [$p,$s]=$this->sample();$this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'missing-images-prepare']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'preview_key'=>str_repeat('0',64)])->assertStatus(422);
        $this->postJson(route('marketing.workspace.storyboard.write',[$p->id,'timing-extend']),['request_id'=>(string)Str::uuid(),'version'=>$s['story_version']-1,'preview_key'=>str_repeat('0',64)])->assertStatus(409);
        $this->assertSame($s['story_version'],Workspace::where('project_id',$p->id)->first()->version);Http::assertNothingSent();Queue::assertNothingPushed();
    }
}
