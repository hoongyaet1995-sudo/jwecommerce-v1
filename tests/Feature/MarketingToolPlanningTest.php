<?php
namespace Tests\Feature;

use App\Models\{MarketingAsset,MarketingProject,MarketingRevision,MarketingProductionPlan,User};
use App\Services\Marketing\{MarketingCapabilities,MarketingToolPlanning,MarketingWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http,Queue,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingToolPlanningTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();Http::preventStrayRequests();Queue::fake();Storage::fake('local');config(['marketing.planning.enabled'=>false,'marketing.planning.project'=>null,'marketing.planning.execution_enabled'=>false]);}
    private function sample():array {
        $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
        $p=app(MarketingWorkflow::class)->create($u,['source_type'=>'theme','theme'=>'JTG factual container introduction']);
        $brief=array_replace($p->brief,['source_confirmed'=>true,'facts_confirmed'=>true,'goal_confirmed'=>true,'audience_confirmed'=>true,'language'=>'English','market'=>'Malaysia','platforms'=>['Facebook']]);
        $concept=['title'=>'Product uses','ratio'=>'9:16','duration'=>15,'recommended'=>true,'hook'=>'A real container','materials'=>'Saved materials','story'=>'Explain confirmed uses','reason'=>'Introduce the product'];$p->update(['brief'=>$brief,'concepts'=>[$concept],'selected_concept'=>0]);
        $path=UploadedFile::fake()->image('container.png',100,100)->store('marketing/'.$p->id,'local');
        $a=MarketingAsset::create(['project_id'=>$p->id,'name'=>'Our container','path'=>$path,'mime'=>'image/png','size'=>Storage::disk('local')->size($path),'authorized'=>true]);
        $scenes=array_map(fn($i)=>['visual'=>'Internal director instruction: move camera '.$i,'voiceover'=>'','subtitle'=>'Container uses '.$i,'material'=>$i===1?'Our actual image':'text-only original graphic','duration'=>5,'asset_ids'=>$i===1?[$a->id]:[]],[1,2,3]);
        $doc=['summary'=>'Factual container uses','duration'=>15,'cta'=>$brief['cta'],'gaps'=>[],'changes'=>[],'scenes'=>$scenes];
        $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$u->id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$brief,'snapshot'=>$p->snapshot,'concept'=>$concept],'confirmed_at'=>now(),'confirmed_by'=>$u->id]);
        $p->update(['current_revision_id'=>$r->id,'confirmed_revision_id'=>$r->id,'status'=>'prepared','stage'=>'confirmed']);
        $proposal=['schema_version'=>1,'route'=>'local_composition','audio_policy'=>'silent','scenes'=>array_map(fn($s,$i)=>['scene_id'=>'scene-'.($i+1),'capability'=>$i===0?'image_motion':'text_card','reason'=>'Use confirmed material to explain this scene.','asset_ids'=>$s['asset_ids'],'catalog_paths'=>[]],$scenes,array_keys($scenes))];
        return [$p->fresh(),$r,$a,$proposal];
    }
    public function test_proposal_is_not_execution_and_makes_no_http():void {
        [$p,$r,,$proposal]=$this->sample();$review=app(MarketingToolPlanning::class)->review($p,$r->document,$proposal);
        $this->assertSame('local_composition',$review['route']);$this->assertSame([], $review['blockers']);$this->assertFalse($review['execution_authorized']);$this->assertSame(0,$review['cost']['new_video_requests']);
        $this->assertCount(3,$review['scenes']);$this->assertSame('scene-2',$review['scenes'][1]['scene_id']);Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_unsupported_or_provisional_capability_blocks_budget():void {
        [$p,$r,,$proposal]=$this->sample();$doc=$r->document;$doc['scenes']=[$doc['scenes'][1]];$doc['scenes'][0]['duration']=15;$doc['scenes'][0]['subtitle']='';$doc['duration']=15;$p->brief=array_replace($p->brief,['language'=>'Mandarin Chinese']);
        $proposal=['schema_version'=>1,'route'=>'native_video','audio_policy'=>'native_audio','scenes'=>[['scene_id'=>'scene-1','capability'=>'native_presenter','reason'=>'A single synthetic presenter explains the company.','asset_ids'=>[],'catalog_paths'=>[]]]];
        $review=app(MarketingToolPlanning::class)->review($p,$doc,$proposal);
        $this->assertStringContainsString('spending authorization',implode(' ',$review['blockers']));$this->assertNull($review['cost']['supplier_estimate_usd']);$this->assertSame(1,$review['cost']['new_video_requests']);
        $this->assertFalse(app(MarketingCapabilities::class)->snapshot()['capabilities']['native_presenter']['execution_enabled']);Http::assertNothingSent();
    }
    public function test_missing_media_wrong_owner_or_hash_blocks():void {
        [$p,$r,$a,$proposal]=$this->sample();$service=app(MarketingToolPlanning::class);$before=$service->review($p,$r->document,$proposal);
        Storage::disk('local')->put($a->path,'broken');$broken=$service->review($p,$r->document,$proposal);$this->assertNotEmpty($broken['blockers']);$this->assertNotSame($before['input_digest'],$broken['input_digest']);
        $other=app(MarketingWorkflow::class)->create(User::factory()->create(),['source_type'=>'theme','theme'=>'Another shop']);$a->update(['project_id'=>$other->id]);$foreign=$service->review($p,$r->document,$proposal);$this->assertNotEmpty($foreign['blockers']);Http::assertNothingSent();
    }
    public function test_internal_instructions_never_become_viewer_copy():void {
        [$p,$r,,$proposal]=$this->sample();$review=app(MarketingToolPlanning::class)->review($p,$r->document,$proposal);
        foreach($review['scenes'] as $i=>$scene){$this->assertSame($r->document['scenes'][$i]['subtitle'],$scene['viewer_copy']['text']);$this->assertStringNotContainsString('director',json_encode($scene['viewer_copy']));}
        $this->expectException(\Illuminate\Validation\ValidationException::class);$proposal['model']='anything';app(MarketingToolPlanning::class)->review($p,$r->document,$proposal);
    }
    public function test_catalog_probe_and_normal_preparation_are_reopenable_but_never_executable():void {
        [$p,$r,,$proposal]=$this->sample();Storage::fake('public');$path=UploadedFile::fake()->image('catalog.png',100,100)->store('catalog','public');
        $p->update(['snapshot'=>array_replace($p->snapshot,['images'=>[['path'=>'storage/'.$path]]])]);
        $doc=$r->document;$doc['scenes'][0]['material']='Catalog storage/'.$path;$doc['scenes'][0]['asset_ids']=[];
        $context=$r->context;$context['snapshot']=$p->snapshot;$r->update(['document'=>$doc,'context'=>$context]);
        config(['marketing.planning.enabled'=>true,'marketing.planning.project'=>$p->id]);
        $request=(string)Str::uuid();$url='/admin/marketing/projects/'.$p->id.'/production';$input=['version'=>$p->version,'revision_id'=>$r->id,'request_id'=>$request];
        $this->post($url,$input)->assertRedirect();$plan=MarketingProductionPlan::sole();
        $this->assertSame('capability-plan-v1',$plan->manifest['recipe']);$this->assertSame('rules_from_saved_storyboard',$plan->manifest['strategy_origin']);$this->assertSame('image/png',$plan->manifest['scenes'][0]['images'][0]['mime']);
        $this->assertSame('blocked',$plan->status);$this->assertSame(0,$plan->manifest['audio']['requests']);
        $this->post($url,$input)->assertRedirect();$this->assertDatabaseCount('marketing_production_plans',1);
        $this->get($url)->assertOk()->assertSee('Planning only')->assertSee('disabled',false);
        $this->post($url.'/'.$plan->id.'/confirm',['version'=>$p->version,'budget_confirmation'=>1])->assertStatus(422);
        $this->post($url.'/'.$plan->id.'/start',['version'=>$p->version])->assertStatus(422);
        $this->assertSame($doc,$r->fresh()->document);$this->assertDatabaseCount('marketing_productions',0);Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_dynamic_storyboard_strategy_is_saved_as_a_new_revision_and_can_be_reopened_without_ai():void {
        [$p,$r,,$proposal]=$this->sample();config(['marketing.planning.enabled'=>true,'marketing.planning.project'=>$p->id]);
        $doc=$r->document;$doc['requirements']=[];unset($doc['gaps']);$doc['production_strategy']=$proposal;
        $facts=['tool_planning'=>true,'brief'=>$p->brief,'assets'=>$p->assets()->get()->toArray(),'draft_contract'=>\App\Services\Marketing\MarketingDraftContract::VERSION];
        $validated=app(\App\Services\Marketing\MarketingWriter::class)->validateOutput($doc,'revise',$facts);
        $this->assertCount(3,$validated['scenes']);$this->assertSame($proposal,$validated['production_strategy']);
        $this->partialMock(\App\Services\Marketing\MarketingWriter::class)->shouldReceive('propose')->once()->withArgs(fn($facts,$action,$feedback)=>$facts['tool_planning']===true&&$action==='revise')->andReturn($validated);
        $request=(string)Str::uuid();$flow=app(MarketingWorkflow::class);$flow->generate($p->id,$p->owner_id,$p->version,'revise','Keep the story, review production tools.',$request);
        $new=$p->fresh()->currentRevision;$this->assertNotSame($r->id,$new->id);$this->assertSame($r->document,$r->fresh()->document);$this->assertSame($proposal,$new->context['production_strategy']['proposal']);$this->assertSame('capability-plan-v1',$new->context['production_recipe']);$this->assertNull($new->confirmed_at);
        $flow->generate($p->id,$p->owner_id,$p->version,'revise','Keep the story, review production tools.',$request);$this->assertDatabaseCount('marketing_revisions',2);
        $this->get('/admin/marketing/projects/'.$p->id)->assertOk()->assertSee('Factual container uses');Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_planning_propose_cannot_spend_even_if_legacy_text_connection_is_enabled():void {
        [$p]=$this->sample();config(['marketing.text_enabled'=>true]);\App\Models\AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'text_model'=>'gpt-5.6-luna','api_key'=>'fixture-only']);
        try{app(\App\Services\Marketing\MarketingWriter::class)->propose(['project_id'=>$p->id,'tool_planning'=>true],'storyboard','');$this->fail('Must not spend');}catch(\Illuminate\Validation\ValidationException $e){$this->assertStringContainsString('tool-routing allowance is required',json_encode($e->errors()));}Http::assertNothingSent();
    }
    public function test_preflight_is_not_bound_to_six_scenes_or_material_position():void {
        [$p,$r,,$proposal]=$this->sample();$service=app(MarketingToolPlanning::class);
        foreach([3,5,6] as $count){
            $doc=$r->document;while(count($doc['scenes'])<$count)$doc['scenes'][]=$doc['scenes'][1];
            $last=array_pop($doc['scenes']);array_unshift($doc['scenes'],$last);$doc['duration']=$count*5;
            $suggestion=$service->fromSaved($p,$doc);$review=$service->review($p,$doc,$suggestion);
            $this->assertCount($count,$review['scenes']);$this->assertSame('text_card',$review['scenes'][0]['capability']);$this->assertSame('image_motion',$review['scenes'][1]['capability']);$this->assertSame([],$review['blockers']);
        }Http::assertNothingSent();
    }
    public function test_hybrid_generation_and_sound_or_overlay_mismatch_are_not_silently_substituted():void {
        [$p,$r,,$proposal]=$this->sample();$doc=$r->document;$doc['scenes'][1]['material']='A new generated illustrative context';
        $proposal['route']='hybrid';$proposal['scenes'][1]['capability']='generated_broll';$review=app(MarketingToolPlanning::class)->review($p,$doc,$proposal);
        $this->assertSame(1,$review['cost']['new_video_requests']);$this->assertNull($review['cost']['supplier_estimate_usd']);$this->assertStringContainsString('not verified',implode(' ',$review['blockers']));
        $proposal['audio_policy']='native_audio';$review=app(MarketingToolPlanning::class)->review($p,$doc,$proposal);$this->assertStringContainsString('audio policy',implode(' ',$review['blockers']));
        $proposal['scenes'][2]['capability']='generated_broll';$review=app(MarketingToolPlanning::class)->review($p,$doc,$proposal);$this->assertStringContainsString('at most one',implode(' ',$review['blockers']));Http::assertNothingSent();
    }
    public function test_unknown_tool_remapped_scene_and_changed_media_invalidate_strategy():void {
        [$p,$r,$a,$proposal]=$this->sample();$service=app(MarketingToolPlanning::class);$saved=$service->review($p,$r->document,$proposal);
        $context=$r->context;$context['production_recipe']=MarketingToolPlanning::RECIPE;$context['production_strategy']=['proposal'=>$proposal,'review'=>$saved];$r->context=$context;
        $a->update(['authorized'=>false]);$manifest=$service->compile($p,$r);$this->assertStringContainsString('changed',implode(' ',$manifest['blockers']));
        foreach(['scene_id'=>'scene-3','capability'=>'run_shell'] as $key=>$value){$bad=$proposal;$bad['scenes'][0][$key]=$value;try{$service->review($p,$r->document,$bad);$this->fail('Reject unknown or remapped capability');}catch(\Illuminate\Validation\ValidationException){$this->assertTrue(true);}}
        Http::assertNothingSent();
    }
    public function test_missing_exact_voice_and_nonvertical_or_unsupported_language_block():void {
        [$p,$r,,$proposal]=$this->sample();$doc=$r->document;$doc['scenes'][0]['voiceover']='Meet our container.';$proposal['audio_policy']='existing_voice';
        $review=app(MarketingToolPlanning::class)->review($p,$doc,$proposal);$this->assertStringContainsString('exact authorized saved voice',implode(' ',$review['blockers']));$this->assertNull($review['cost']['supplier_estimate_usd']);
        $concepts=$p->concepts;$concepts[0]['ratio']='16:9';$p->concepts=$concepts;$p->brief=array_replace($p->brief,['language'=>'Mandarin']);
        $review=app(MarketingToolPlanning::class)->review($p,$doc,$proposal);$this->assertStringContainsString('9:16',implode(' ',$review['blockers']));$this->assertStringContainsString('English',implode(' ',$review['blockers']));Http::assertNothingSent();
    }
    public function test_concept_capability_hints_keep_narratives_and_flag_unverified_needs():void {
        $concepts=[];foreach(['use_scenario','feature_detail','brand_story'] as $i=>$approach)$concepts[]=['title'=>'Different angle '.$i,'approach'=>$approach,'hook'=>'Hook '.$i,'story'=>'Specific story '.$i,'materials'=>'Required scene materials','duration'=>15,'ratio'=>'9:16','recommended'=>$i===0,'reason'=>'Appropriate for this business','capabilities'=>[$i===2?'native_presenter':'image_motion']];
        $result=app(\App\Services\Marketing\MarketingWriter::class)->validateOutput(['concepts'=>$concepts],'concepts',['tool_planning'=>true,'brief'=>['language'=>'English']]);
        $this->assertSame($concepts[0]['story'],$result['concepts'][0]['story']);$this->assertFalse($result['concepts'][2]['capability_check']['execution_authorized']);$this->assertNotEmpty($result['concepts'][2]['capability_check']['blockers']);Http::assertNothingSent();
    }
    public function test_saved_clip_with_audio_cannot_be_silently_muted_or_mixed():void {
        [$p,$r,$a,$proposal]=$this->sample();$path='marketing/'.$p->id.'/clip.mp4';Storage::disk('local')->put($path,str_repeat('media',100));$a->update(['path'=>$path,'mime'=>'video/mp4']);
        $tools=$this->mock(\App\Services\Marketing\MarketingMediaTools::class);$tools->shouldReceive('probe')->andReturn(['streams'=>[['codec_type'=>'video','codec_name'=>'h264','width'=>720,'height'=>1280,'nb_read_frames'=>'120'],['codec_type'=>'audio','codec_name'=>'aac']],'format'=>['duration'=>5]]);$tools->shouldReceive('run')->andReturn('');
        $proposal['scenes'][0]['capability']='existing_video';$review=app(MarketingToolPlanning::class)->review($p,$r->document,$proposal);
        $this->assertStringContainsString('source audio',implode(' ',$review['blockers']));Http::assertNothingSent();
    }
    public function test_malformed_proposal_and_associative_scene_lists_are_controlled_validation_errors():void {
        [$p,$r,,$proposal]=$this->sample();$service=app(MarketingToolPlanning::class);
        foreach([[],array_replace($proposal,['scenes'=>['foreign-key'=>$proposal['scenes'][0]]])] as $bad){
            try{$service->review($p,$r->document,$bad);$this->fail('Malformed proposal must not be accepted');}catch(\Illuminate\Validation\ValidationException){$this->assertTrue(true);}
        }Http::assertNothingSent();
    }
    public function test_supporting_generated_shot_may_keep_existing_unified_narration():void {
        [$p,$r,,$proposal]=$this->sample();$doc=$r->document;$doc['scenes'][1]['voiceover']='Consider the intended use.';$doc['scenes'][1]['material']='A planned illustrative scene, not our actual warehouse.';
        $proposal['scenes'][1]['capability']='generated_broll';$proposal['route']='hybrid';$proposal['audio_policy']='existing_voice';
        $review=app(MarketingToolPlanning::class)->review($p,$doc,$proposal);
        $this->assertStringNotContainsString('without generated dialogue',implode(' ',$review['blockers']));$this->assertSame($doc['scenes'][1]['voiceover'],$review['scenes'][1]['voiceover']);$this->assertNull($review['cost']['supplier_estimate_usd']);Http::assertNothingSent();
    }
    public function test_invalid_strategy_keeps_history_and_foreign_or_stale_preparation_is_denied():void {
        [$p,$r,,$proposal]=$this->sample();config(['marketing.planning.enabled'=>true,'marketing.planning.project'=>$p->id]);$owner=User::findOrFail($p->owner_id);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$url='/admin/marketing/projects/'.$p->id.'/production';$input=['version'=>$p->version,'revision_id'=>$r->id,'request_id'=>(string)Str::uuid()];
        $this->post($url,$input)->assertNotFound();$this->actingAs($owner);$this->post($url,array_replace($input,['version'=>$p->version+1]))->assertStatus(409);$this->assertDatabaseCount('marketing_production_plans',0);
        $bad=$r->document;$bad['production_strategy']=$proposal;$bad['production_strategy']['scenes'][0]['capability']='untrusted_tool';$this->partialMock(\App\Services\Marketing\MarketingWriter::class)->shouldReceive('propose')->once()->andReturn($bad);
        try{app(MarketingWorkflow::class)->generate($p->id,$p->owner_id,$p->version,'revise','Review production.',(string)Str::uuid());$this->fail('Invalid strategy must fail');}catch(\Illuminate\Validation\ValidationException){$this->assertSame($r->id,$p->fresh()->current_revision_id);$this->assertSame($r->document,$r->fresh()->document);$this->assertDatabaseCount('marketing_revisions',1);}
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
}
