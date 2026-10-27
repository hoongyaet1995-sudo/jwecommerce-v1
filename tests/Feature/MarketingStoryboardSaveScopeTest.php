<?php
namespace Tests\Feature;

use App\Models\MarketingRevision;
use App\Models\MarketingSceneRevision;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use App\Services\Marketing\MarketingStoryboardDirector;
use App\Services\Marketing\MarketingStoryboardSaveScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardSaveScopeTest extends TestCase
{
    use RefreshDatabase, MarketingResearchFixtures;
    public function test_timing_uses_the_same_speech_estimate_and_supports_three_seconds():void {
        $timing=app(\App\Services\Marketing\MarketingStoryboardTiming::class);
        $scene=['scene_id'=>'one','seconds'=>3,'voiceover'=>'先写清楚用途，再看货柜形式。'];
        $this->assertContains('voiceover_needs_more_time',$timing->calculate([$scene])['reasons']);
        $timing->validateProposal([],[$scene]);
        $this->assertLessThanOrEqual(5,app(MarketingStoryboardSaveScope::class)->speechSeconds('A complete spoken sentence'));
    }
    public function test_extra_frame_uses_only_approved_end_and_rejects_changed_reference_before_http():void {
        [$p,$s,$scene]=$this->sample();
        $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('anchor.png'),true);
        $frames=['start_frame'=>['asset'=>$ref,'readiness'=>'qa_passed'],'end_frame'=>['asset'=>$ref,'readiness'=>'pending_sync']];
        $task=new \App\Models\MarketingStoryboardTask(['project_id'=>$p->id,'result'=>['frames'=>$frames]]);
        $images=app(\App\Services\Marketing\MarketingStoryboardImages::class);
        $this->assertArrayNotHasKey('scene_end',$images->frameReferences($task,$scene,$s['profile'],'extra_image'));
        $frames['end_frame']['readiness']='qa_passed';$task->result=['frames'=>$frames];
        $this->assertSame($ref['sha256'],$images->frameReferences($task,$scene,$s['profile'],'extra_image')['scene_end']['sha256']);
        $frames['end_frame']['asset']['sha256']=str_repeat('0',64);$task->result=['frames'=>$frames];
        try{$images->frameReferences($task,$scene,$s['profile'],'extra_image');$this->fail('Changed frame must be rejected');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
        Http::assertNothingSent();
    }
    public function test_ai_shortens_copy_in_one_analysis_and_preserves_adjacent_scene():void {
        [$p,$s,$before]=$this->sample();
        $s=app(MarketingStoryboardWorkspace::class)->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'insert','after_id'=>$before['scene_id'],'content'=>$before]);
        $next=$s['scenes'][1];
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'FAKE timing','project_id'=>$p->id,'owner_id'=>$p->owner_id,'roles'=>['semantic'],'expires_at'=>now()->addHour()->toIso8601String(),'max_total_microusd'=>1000,'max_calls'=>2],'marketing.storyboard.roles.semantic'=>['model'=>'fixture-model','version'=>'timing1','cap_microusd'=>100,'pricing_verified_at'=>now()->toIso8601String()]]);
        $calls=0;app()->instance('marketing.storyboard.semantic_transport',function($payload)use($before,$next,&$calls){$calls++;$this->assertSame($next['scene_id'],$payload['intent']['adjacent_scenes']['next']['scene_id']);$this->assertNull($payload['intent']['adjacent_scenes']['previous']);$this->assertSame(3,$payload['intent']['requested_seconds']);$c=$before;$c['voiceover']='先看用途。';$c['subtitle']='先看用途';return ['scenes'=>[$c]];});
        $id=(string)Str::uuid();$cmd=['type'=>'edit','mode'=>'ai','scene_id'=>$before['scene_id'],'intent'=>'缩短这段，让AI整理口播','requested_seconds'=>3,'update_scope'=>['images'=>false,'director'=>true]];
        $service=app(MarketingStoryboardDirector::class);$out=$service->change($p->id,$p->owner_id,$id,$s['story_version'],$cmd);
        $this->assertSame(3,$out['snapshot']['scenes'][0]['seconds']);$this->assertSame('先看用途。',$out['snapshot']['scenes'][0]['voiceover']);$this->assertSame($next['revision_id'],$out['snapshot']['scenes'][1]['revision_id']);
        $service->change($p->id,$p->owner_id,$id,$s['story_version'],$cmd);$this->assertSame(1,$calls);Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_static_image_reuse_ignores_speech_and_beat_timing_but_detects_visual_changes():void {
        [$p,$s,$scene]=$this->sample();$images=app(\App\Services\Marketing\MarketingStoryboardImages::class);$other=$scene;
        $other['voiceover']='新的旁白';$other['subtitle']='新的字幕';$other['seconds']=9;$other['director']['beats']=[['start'=>0,'end'=>9,'action'=>'观察画面后留白']];$other['director']['motion']='慢慢推进，留白观察';
        $this->assertSame($images->reuseSignature($scene,'start_frame',$s['profile']),$images->reuseSignature($other,'start_frame',$s['profile']));
        $other['visual']='Different product and background';$this->assertNotSame($images->reuseSignature($scene,'start_frame',$s['profile']),$images->reuseSignature($other,'start_frame',$s['profile']));Http::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_pacing_change_keeps_accepted_image_and_audit_without_jobs_or_reconfirmation():void {
        [$p,$s,$scene]=$this->sample();$ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('original.png'),true);
        $s=app(MarketingStoryboardWorkspace::class)->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','scene_id'=>$scene['scene_id'],'content'=>array_intersect_key($scene,array_flip(['seconds','message','visual','voiceover','subtitle'])),'asset_id'=>$ref['asset_id']]);$before=$s['scenes'][0];
        $next=$before;foreach($next['frames'] as &$f)$f=array_intersect_key($f,array_flip(['required','instruction','state','person_presence']));unset($f);$next['voiceover']='停一下。';$next['subtitle']='停一下';$next['director']['motion']='缓慢推进，留白观察';$next['director']['beats']=[['start'=>0,'end'=>5,'action'=>'观察已有画面']];
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','mode'=>'manual','scene_id'=>$scene['scene_id'],'content'=>$next,'update_scope'=>['director'=>true,'images'=>false]]);
        $after=$out['snapshot']['scenes'][0];$this->assertSame($ref['sha256'],$after['frames']['start_frame']['asset']['sha256']);$this->assertSame('employee_declared_local_validated',$after['frames']['start_frame']['readiness']);$this->assertEmpty($after['frames']['start_frame']['existing_asset_review_required']??false);$this->assertTrue($out['snapshot']['readiness']);$this->assertSame([], $out['preparation']['tasks']);Http::assertNothingSent();Queue::assertNothingPushed();
    }

    private function sample():array {
        Http::preventStrayRequests(); Queue::fake();
        $p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>[['visual'=>'Warehouse product','voiceover'=>'先写清楚用途，再看货柜形式。','subtitle'=>'先写清楚用途，再看货柜形式。','duration'=>5]]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        return [$p,$s,$s['scenes'][0]];
    }
    public function test_image_only_preserves_director_and_copy_but_changes_image_signature():void {
        [$p,$s,$before]=$this->sample();
        $id=(string)Str::uuid();$cmd=['type'=>'edit','mode'=>'ai','scene_id'=>$before['scene_id'],'intent'=>'收紧构图，保持原场景','update_scope'=>['images'=>true,'director'=>false,'frames'=>['start_frame']]];
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$cmd);
        $after=$out['snapshot']['scenes'][0];
        foreach(['director','scene_design','message','visual','voiceover','subtitle','seconds'] as $k)$this->assertSame($before[$k]??null,$after[$k]??null,$k);
        $this->assertNotSame($before['image_signature'],$after['image_signature']);
        $this->assertSame(['start_frame'],$after['image_adjustment']['roles']);
        $duplicate=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$cmd);
        $this->assertSame($out['snapshot'],$duplicate['snapshot']);
        Http::assertNothingSent();
    }
    public function test_manual_wording_edit_keeps_existing_rhythm_and_material_plan_when_visual_and_seconds_are_unchanged():void {
        [$p,$s,$scene]=$this->sample();$c=$scene;
        $c['voiceover']='先停一下。';$c['rhythm']=['speech_start'=>0,'speech_end'=>3,'silent_beats'=>[['start'=>3,'end'=>5,'purpose'=>'观察现有画面']],'reason'=>'文字与动作衔接'];
        $c['material_plan']=['mode'=>'single_image','roles'=>['start_frame'],'reason'=>'沿用首帧'];
        $s=app(MarketingStoryboardWorkspace::class)->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','scene_id'=>$scene['scene_id'],'content'=>$c]);$before=$s['scenes'][0];
        $manual=array_intersect_key($before,array_flip(['message','visual','voiceover','subtitle','seconds']));$manual['voiceover']='停一下。';
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','mode'=>'manual','refine_manual'=>false,'scene_id'=>$scene['scene_id'],'content'=>$manual,'update_scope'=>['director'=>true,'images'=>false]]);
        $this->assertSame($before['rhythm'],$out['snapshot']['scenes'][0]['rhythm']);$this->assertSame($before['material_plan'],$out['snapshot']['scenes'][0]['material_plan']);Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_image_only_can_adjust_a_review_candidate_without_validating_snapshot_metadata_as_authoring():void {
        [$p,$s,$scene]=$this->sample();
        $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('candidate.png'),true);
        $old=MarketingSceneRevision::findOrFail($scene['revision_id']);$c=$old->content;
        $c['frames']['start_frame']['candidate_asset']=$ref;
        $c['frames']['start_frame']['qa']=['status'=>'revise','evidence'=>['checks'=>array_fill_keys(\App\Services\Marketing\MarketingMaterialVisionClient::CHECKS,'fail')]];
        $next=MarketingSceneRevision::create(['scene_id'=>$old->scene_id,'parent_id'=>$old->id,'content'=>$c,'signature'=>$old->signature]);
        \App\Models\MarketingScene::findOrFail($old->scene_id)->update(['current_revision_id'=>$next->id]);
        $id=(string)Str::uuid();$command=['type'=>'edit','mode'=>'ai','scene_id'=>$scene['scene_id'],'intent'=>'Remove overlay text only','update_scope'=>['images'=>true,'director'=>false,'frames'=>['start_frame']]];
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$command);
        $after=$out['snapshot']['scenes'][0];
        $this->assertSame($scene['director'],$after['director']);
        $this->assertSame('Remove overlay text only',$after['image_adjustment']['instruction']);
        $this->assertSame($ref['sha256'],MarketingSceneRevision::findOrFail($next->id)->content['frames']['start_frame']['candidate_asset']['sha256']);
        $this->assertSame($out['snapshot'],app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$command)['snapshot']);
        Http::assertNothingSent();
    }
    public function test_director_only_keeps_prior_image_as_pending_sync_without_promoting_it():void {
        [$p,$s,$before]=$this->sample();
        $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('prior.png'),true);
        $c=$before;
        $s=app(MarketingStoryboardWorkspace::class)->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','scene_id'=>$before['scene_id'],'content'=>$c,'frame_assets'=>['start_frame'=>$ref['asset_id']]]);
        $c=$s['scenes'][0];$c['visual']='New background design';
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','mode'=>'manual','scene_id'=>$before['scene_id'],'content'=>$c,'update_scope'=>['images'=>false,'director'=>true]]);
        $after=$out['snapshot']['scenes'][0];$this->assertSame($ref['asset_id'],$after['frames']['start_frame']['asset']['asset_id']);$this->assertSame('pending_sync',$after['frames']['start_frame']['readiness']);$this->assertFalse($out['snapshot']['readiness']);Queue::assertNothingPushed();
    }
    public function test_selected_frame_preparation_only_generates_that_role_with_separate_image_instruction():void {
        [$p,$s,$before]=$this->sample();
        $c=$before;foreach($c['frames'] as &$f)$f['required']=true;unset($f);
        $s=app(MarketingStoryboardWorkspace::class)->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','scene_id'=>$before['scene_id'],'content'=>$c]);$before=$s['scenes'][0];
        $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('candidate.png'),true);
        $calls=[];app()->instance('marketing.storyboard.image_transport',function($compiled)use($ref,&$calls){$calls[]=$compiled;return ['asset'=>$ref,'actual_microusd'=>10];});
        app()->instance('marketing.storyboard.qa_transport',fn()=>['status'=>'pass']);
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'FAKE selected-frame grant','project_id'=>$p->id,'owner_id'=>$p->owner_id,'roles'=>['image','qa'],'expires_at'=>now()->addHour()->toIso8601String(),'max_total_microusd'=>1000,'max_calls'=>3]]);
        $command=['type'=>'edit','mode'=>'ai','scene_id'=>$before['scene_id'],'intent'=>'Tighter crop','update_scope'=>['images'=>true,'director'=>false,'frames'=>['start_frame']]];
        $response=$this->postJson(route('marketing.workspace.storyboard',[$p->id]).'/changes',['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>$command]);$response->assertOk();$out=$response->json();
        $tasks=$out['preparation'];$this->assertCount(1,$tasks['tasks']);Queue::assertPushed(\App\Jobs\ProcessMarketingStoryboardPreparation::class,1);
        $prep=app(\App\Services\Marketing\MarketingStoryboardPreparation::class);$prep->process($tasks['tasks'][0]);$prep->process($tasks['tasks'][0]);
        $this->assertCount(1,$calls);$this->assertSame('start_frame',$calls[0]['role']);$this->assertStringContainsString('Tighter crop',$calls[0]['prompt']);$this->assertSame($before['director'],$calls[0]['director']);Http::assertNothingSent();
        $this->assertSame('partial',\App\Models\MarketingStoryboardTask::findOrFail($tasks['tasks'][0])->status);
    }
    public function test_director_only_saves_without_creating_image_tasks():void {
        [$p,$s,$before]=$this->sample();$c=$before;$c['visual']='Updated warehouse layout';
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','mode'=>'manual','scene_id'=>$before['scene_id'],'content'=>$c,'update_scope'=>['images'=>false,'director'=>true]]);
        $this->assertSame('Updated warehouse layout',$out['snapshot']['scenes'][0]['visual']);
        $this->assertSame([], $out['preparation']['tasks']);Queue::assertNothingPushed();Http::assertNothingSent();
    }
    public function test_invalid_scope_and_image_only_timing_are_rejected_before_transport():void {
        [$p,$s,$before]=$this->sample();
        foreach([['images'=>false,'director'=>false],['images'=>true,'director'=>false,'frames'=>[]],['images'=>true,'director'=>false,'frames'=>['video']]] as $scope){
            try{app(MarketingStoryboardSaveScope::class)->validate(['type'=>'edit','update_scope'=>$scope],$before);$this->fail('Must reject invalid scope');}catch(\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e instanceof \Symfony\Component\HttpKernel\Exception\HttpException?$e->getStatusCode():422);}
        }
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(MarketingStoryboardSaveScope::class)->validate(['type'=>'edit','requested_seconds'=>3,'update_scope'=>['images'=>true,'director'=>false]],$before);
    }
    public function test_ai_three_second_target_reaches_director_instead_of_requiring_employee_copy():void {
        [$p,$s,$before]=$this->sample();
        $this->assertGreaterThan(3,app(MarketingStoryboardSaveScope::class)->speechSeconds($before['voiceover']));
        $scope=app(MarketingStoryboardSaveScope::class)->validate(['type'=>'edit','mode'=>'ai','requested_seconds'=>3,'update_scope'=>['images'=>false,'director'=>true]],$before);
        $this->assertTrue($scope['director']);
    }
    public function test_http_director_only_three_second_confirmed_copy_updates_design_without_image_jobs():void {
        [$p,$s,$before]=$this->sample();
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'FAKE scope only','project_id'=>$p->id,'owner_id'=>$p->owner_id,'roles'=>['semantic'],'expires_at'=>now()->addHour()->toIso8601String(),'max_total_microusd'=>1000,'max_calls'=>2],'marketing.storyboard.roles.semantic'=>['model'=>'fixture-model','version'=>'scope1','cap_microusd'=>100,'pricing_verified_at'=>now()->toIso8601String()]]);
        $seen=0;app()->instance('marketing.storyboard.semantic_transport',function($payload)use($before,&$seen){$seen++;$c=$before;$c['visual']='Fictional warehouse design';$c['fact_refs']=['scene.'.$before['scene_id'].'.voiceover'];$this->assertSame('先看用途，再选货柜。',$payload['facts']['employee.confirmed_voiceover']);return ['scenes'=>[$c],'missing_facts'=>[]];});
        $command=['type'=>'edit','mode'=>'ai','scene_id'=>$before['scene_id'],'intent'=>'缩短表达','requested_seconds'=>3,'timing_resolution'=>'shorten','confirmed_voiceover'=>'先看用途，再选货柜。','update_scope'=>['images'=>false,'director'=>true]];
        $response=$this->postJson(route('marketing.workspace.storyboard',[$p->id]).'/changes',['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>$command]);
        $response->assertOk()->assertJsonPath('snapshot.scenes.0.seconds',3)->assertJsonPath('snapshot.scenes.0.voiceover','先看用途，再选货柜。')->assertJsonPath('preparation.tasks',[]);
        $this->assertSame(1,$seen);Queue::assertNothingPushed();Http::assertNothingSent();
    }
    public function test_image_only_end_frame_keeps_start_but_marks_dependent_extra_stale():void {
        [$p,$s,$before]=$this->sample();
        $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('ready.png'),true);
        $c=$before;foreach($c['frames'] as &$f)$f['required']=true;unset($f);
        $s=app(MarketingStoryboardWorkspace::class)->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','scene_id'=>$before['scene_id'],'content'=>$c,'frame_assets'=>array_fill_keys(['start_frame','end_frame','extra_image'],$ref['asset_id'])]);
        $scene=$s['scenes'][0];
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','mode'=>'ai','scene_id'=>$scene['scene_id'],'intent'=>'Refine ending pose within saved action','update_scope'=>['images'=>true,'director'=>false,'frames'=>['end_frame']]]);
        $after=$out['snapshot']['scenes'][0];
        $this->assertSame($scene['frames']['start_frame']['asset'],$after['frames']['start_frame']['asset']);
        $this->assertSame('employee_declared_local_validated',$after['frames']['start_frame']['readiness']);
        $this->assertSame($scene['frames']['extra_image']['asset'],$after['frames']['extra_image']['asset']);
        $this->assertSame('pending_sync',$after['frames']['extra_image']['readiness']);
        $this->assertFalse($out['snapshot']['readiness']);
        $this->assertNull($out['snapshot']['scenes'][0]['frames']['end_frame']['asset']);Http::assertNothingSent();
    }
    public function test_rewrite_and_exact_duration_are_rejected_before_semantic_call():void {
        [$p,$s,$before]=$this->sample();$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(MarketingStoryboardSaveScope::class)->validate(['type'=>'edit','rewrite'=>true,'requested_seconds'=>3,'timing_resolution'=>'shorten','confirmed_voiceover'=>'先看用途。','update_scope'=>['images'=>true,'director'=>true]],$before);
    }
    public function test_creative_dialogue_does_not_require_exact_historical_fact_fields():void {
        [$p,$s,$before]=$this->sample();$c=$before;$c['voiceover']='红、蓝、白，让你的仓储更出彩。';$c['subtitle']='红 蓝 白';$c['fact_refs']=[];
        $draft=app(\App\Services\Marketing\MarketingStoryboardSemanticClient::class)->validateDraft(['scenes'=>[$c],'missing_facts'=>['颜色是否有库存']],[],true);
        $this->assertSame($c['voiceover'],$draft['scenes'][0]['voiceover']);
    }
    public function test_manual_director_refinement_preserves_authored_copy_and_reuses_analysis():void {
        [$p,$s,$before]=$this->sample();
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'FAKE manual refinement','project_id'=>$p->id,'owner_id'=>$p->owner_id,'roles'=>['semantic'],'expires_at'=>now()->addHour()->toIso8601String(),'max_total_microusd'=>1000,'max_calls'=>2],'marketing.storyboard.roles.semantic'=>['model'=>'fixture','version'=>'manual1','cap_microusd'=>100,'pricing_verified_at'=>now()->toIso8601String()]]);
        $calls=0;app()->instance('marketing.storyboard.semantic_transport',function($payload)use($before,&$calls){$calls++;$this->assertSame('人物飞过三色货柜',$payload['intent']['authored_content']['visual']);$c=$before;$c['visual']='Fly through red blue white warehouse';$c['director']['framing']='Wide flying shot';$c['voiceover']='模型擅改的口播';$c['subtitle']='模型擅改的字幕';$c['fact_refs']=[];return ['scenes'=>[$c]];});
        $c=['seconds'=>5,'message'=>'颜色选择','visual'=>'人物飞过三色货柜','voiceover'=>'红蓝白，自由搭配。','subtitle'=>"红色\n蓝色\n白色"];
        $cmd=['type'=>'edit','mode'=>'manual','refine_manual'=>true,'scene_id'=>$before['scene_id'],'content'=>$c,'update_scope'=>['images'=>false,'director'=>true]];$id=(string)Str::uuid();
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$cmd);$after=$out['snapshot']['scenes'][0];
        foreach(['seconds','message','voiceover','subtitle'] as $k)$this->assertSame($c[$k],$after[$k]);$this->assertSame('Wide flying shot',$after['director']['framing']);
        app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$cmd);$this->assertSame(1,$calls);Queue::assertNothingPushed();Http::assertNothingSent();
    }
    public function test_manual_opt_out_does_not_analyse_generate_or_claim_complete_design():void {
        [$p,$s,$before]=$this->sample();$c=['seconds'=>5,'message'=>'自填信息','visual'=>'飞过货柜','voiceover'=>'','subtitle'=>''];
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','mode'=>'manual','refine_manual'=>false,'scene_id'=>$before['scene_id'],'content'=>$c]);
        $this->assertTrue($out['snapshot']['scenes'][0]['director_needs_detail']);$this->assertFalse($out['snapshot']['readiness']);$this->assertSame('director_detail_required',$out['preparation']['blockers'][0]['code']);Queue::assertNothingPushed();Http::assertNothingSent();
    }
    public function test_manual_refined_insert_completes_all_three_frames_before_handoff():void {
        [$p,$s,$before]=$this->sample();config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'FAKE manual full','project_id'=>$p->id,'owner_id'=>$p->owner_id,'roles'=>['semantic','image','qa'],'expires_at'=>now()->addHour()->toIso8601String(),'max_total_microusd'=>1000,'max_calls'=>8]]);
        foreach(['semantic','image','qa'] as $role)config(['marketing.storyboard.roles.'.$role=>['model'=>'fixture','version'=>'manual-full','cap_microusd'=>100,'pricing_verified_at'=>now()->toIso8601String()]]);
        app()->instance('marketing.storyboard.semantic_transport',function($payload)use($before){$c=$before;$c['visual']='Detailed flying shot';$c['director']['framing']='Wide';foreach($c['frames'] as &$f)$f['required']=true;unset($f);return ['scenes'=>[$c]];});
        $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('frames.png'),true);$roles=[];
        $s=app(MarketingStoryboardWorkspace::class)->apply($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'edit','scene_id'=>$before['scene_id'],'content'=>$before,'frame_assets'=>['start_frame'=>$ref['asset_id']]]);$before=$s['scenes'][0];
        app()->instance('marketing.storyboard.image_transport',function($compiled)use($ref,&$roles){$roles[]=$compiled['role'];return ['asset'=>$ref,'actual_microusd'=>10];});app()->instance('marketing.storyboard.qa_transport',fn()=>['status'=>'pass']);
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['type'=>'insert','after_id'=>$before['scene_id'],'mode'=>'manual','refine_manual'=>true,'content'=>['seconds'=>5,'message'=>'起飞','visual'=>'飞过三色货柜','voiceover'=>'','subtitle'=>'']]);
        $task=$out['preparation']['tasks'][0];for($i=0;$i<3;$i++)app(\App\Services\Marketing\MarketingStoryboardPreparation::class)->process($task);
        $now=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);$new=$now['scenes'][1];
        $this->assertSame(['start_frame','end_frame','extra_image'],$roles);$this->assertTrue(app(\App\Services\Marketing\MarketingStoryboardPackage::class)->ready($new));$this->assertSame('',$new['voiceover']);
        $this->assertSame($before['revision_id'],$now['scenes'][0]['revision_id']);Http::assertNothingSent();
        $this->assertTrue($now['readiness']);
        $handoff=app(\App\Services\Marketing\MarketingStoryboardHandoff::class)->build($p->id,$p->owner_id,$now['story_id']);
        $this->assertSame('step3-prepared',$handoff['stage']);$this->assertFalse($handoff['voice_route']['formal_audio_generated']);
    }
    public function test_creative_insert_continues_with_obsolete_missing_facts_and_generates_fictional_frames():void {
        [$p,$s,$before]=$this->sample();
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'FAKE creative','project_id'=>$p->id,'owner_id'=>$p->owner_id,'roles'=>['semantic','image','qa'],'expires_at'=>now()->addHour()->toIso8601String(),'max_total_microusd'=>1000,'max_calls'=>8]]);
        foreach(['semantic','image','qa'] as $role)config(['marketing.storyboard.roles.'.$role=>['model'=>'fixture','version'=>'creative1','cap_microusd'=>100,'pricing_verified_at'=>now()->toIso8601String()]]);
        $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('creative.png'),true);
        $analyses=0;$images=[];
        app()->instance('marketing.storyboard.semantic_transport',function($payload)use($before,&$analyses){$analyses++;$c=$before;$c['visual']='KOL flies through a fictional warehouse with red blue white containers';$c['voiceover']='红蓝白，创意起飞！';$c['subtitle']='创意起飞';$c['fact_refs']=[];$c['director']['subject']=$c['visual'];return ['scenes'=>[$c],'missing_facts'=>['没有真实仓库地址','未确认颜色库存']];});
        app()->instance('marketing.storyboard.image_transport',function($compiled)use($ref,&$images){$images[]=$compiled;return ['asset'=>$ref,'actual_microusd'=>10];});app()->instance('marketing.storyboard.qa_transport',fn()=>['status'=>'pass']);
        $command=['type'=>'insert','mode'=>'ai','after_id'=>$before['scene_id'],'intent'=>'走进虚构仓库介绍红蓝白货柜，人物飞起来'];$id=(string)Str::uuid();
        $out=app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$command);
        $this->assertCount(2,$out['snapshot']['scenes']);$this->assertSame($before['revision_id'],$out['snapshot']['scenes'][0]['revision_id']);
        $prep=app(\App\Services\Marketing\MarketingStoryboardPreparation::class);foreach($out['preparation']['tasks'] as $task)$prep->process($task);
        $this->assertCount(1,$images);$this->assertStringContainsString('flies',$images[0]['prompt']);$this->assertSame('employee_directed_marketing_v1',$images[0]['creative_policy']);
        app(MarketingStoryboardDirector::class)->change($p->id,$p->owner_id,$id,$s['story_version'],$command);$this->assertSame(1,$analyses);Http::assertNothingSent();
    }
}
