<?php
namespace Tests\Feature;

use App\Models\{MarketingAsset,MarketingProject,MarketingRevision,MarketingProductionPlan,MarketingProduction};
use App\Services\Marketing\{MarketingProductionCompiler,MarketingMediaTools,MarketingProductionWorkspace,MarketingVoiceLibrary,MarketingProductionFailure};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http};
use Tests\TestCase;

class MarketingHybridProductionTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): array
    {
        Storage::fake('local'); Http::preventStrayRequests();
        $u=\App\Models\User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $p=app(\App\Services\Marketing\MarketingWorkflow::class)->create($u,['source_type'=>'theme','theme'=>'A reusable business offer']);
        Storage::disk('local')->put('marketing/'.$p->id.'/clip.mp4','bounded fixture media');
        $a=MarketingAsset::create(['project_id'=>$p->id,'name'=>'Authorized illustrative clip','path'=>'marketing/'.$p->id.'/clip.mp4','mime'=>'video/mp4','size'=>21,'authorized'=>true]);
        $this->mock(MarketingMediaTools::class)->shouldReceive('probe')->andReturn(['streams'=>[['codec_type'=>'video','codec_name'=>'h264','width'=>720,'height'=>1280,'nb_read_frames'=>'120','nb_frames'=>'120']], 'format'=>['duration'=>'5']]);
        app(MarketingMediaTools::class)->shouldReceive('run')->andReturn('');
        $scenes=[];foreach(range(1,6) as $i)$scenes[]=['visual'=>'An approved illustrative scene','material'=>$i===1?'Existing authorized video clip':'text-only original graphic','voiceover'=>'An approved sentence.','subtitle'=>'A clear message','duration'=>5,'asset_ids'=>$i===1?[$a->id]:[]];
        $path=\Illuminate\Http\UploadedFile::fake()->image('product.png',100,100)->store('marketing/'.$p->id,'local');
        $photo=MarketingAsset::create(['project_id'=>$p->id,'name'=>'Approved product','path'=>$path,'mime'=>'image/png','size'=>Storage::disk('local')->size($path),'authorized'=>true]);
        foreach([1,4] as $i)$scenes[$i]['asset_ids']=[$photo->id];
        $r=new MarketingRevision(['document'=>['scenes'=>$scenes,'duration'=>30,'summary'=>'One use scenario','cta'=>'Request a quote'],'context'=>['concept'=>['ratio'=>'9:16'],'production_recipe'=>'use-scenario-hybrid-v1','presentation'=>['brand'=>['name'=>'ACME','descriptor'=>'BUSINESS SERVICES'],'scenes'=>array_map(fn($layout)=>['layout'=>$layout,'heading'=>'An approved heading','lines'=>['Equipment','Materials','General goods'],'body'=>'Confirmed uses only.'],['need_open','product_intro','use_map','business_uses','requirements','cta_close'])]]]);
        $p->brief=array_replace($p->brief,['language'=>'English']);
        return [$p,$r,$a];
    }
    public function test_video_change_preflights_cached_voice_and_preserves_measured_hold_into_workspace():void
    {
        [$p,$r]=$this->fixture();$compiler=app(MarketingProductionCompiler::class);$old=$compiler->compile($p,$r);
        $context=$r->context;$context['production_change']=['production_id'=>(string)\Illuminate\Support\Str::uuid(),'planned_scenes'=>$old['scenes'],'scene_timings'=>array_map(fn($i)=>['frames'=>$i===5?139:120],range(0,5))];$r->context=$context;
        $doc=$r->document;$doc['scenes'][5]['duration']=7;$doc['duration']=32;$r->document=$doc;
        $m=$compiler->compile($p,$r);$this->assertFalse($m['change_check']['voice_reusable']);$this->assertNotEmpty($m['blockers']);
        $this->assertTrue($m['audio']['reuse_only']);$this->assertNull($m['audio']['credit_authorization']);$this->assertSame(0,$m['budget']['cap_microusd']);
        $this->assertSame(187,$m['scenes'][5]['minimum_frames']);
        app(MarketingMediaTools::class)->shouldReceive('audioDuration')->andReturn(2.0);
        $text=$m['audio']['text'];$chars=mb_str_split($text);$alignment=['characters'=>$chars,'character_start_times_seconds'=>array_map(fn($i)=>$i*.01,array_keys($chars)),'character_end_times_seconds'=>array_map(fn($i)=>($i+1)*.01,array_keys($chars))];
        $plan=new MarketingProductionPlan(['project_id'=>$p->id,'manifest'=>$m]);
        app(MarketingVoiceLibrary::class)->import($plan,'ID3-valid fixture voice data',$alignment,[]);
        $m=$compiler->compile($p,$r);$this->assertTrue($m['change_check']['voice_reusable']);$this->assertSame([],$m['blockers']);
        $plan->manifest=$m;$plan->snapshot=['source'=>['title'=>'Product'],'revision_number'=>2,'brief'=>[]];
        $task=new MarketingProduction(['id'=>(string)\Illuminate\Support\Str::uuid(),'project_id'=>$p->id]);
        $directory=app(MarketingProductionWorkspace::class)->stage($task,$plan);$input=json_decode(file_get_contents($directory.'/input.json'),true);
        $this->assertSame(187,$input['manifest']['scenes'][5]['minimum_frames']);
        Http::assertNothingSent();
    }

    public function test_hybrid_recipe_compiles_exact_private_clip_with_zero_new_paid_calls():void
    {
        [$p,$r,$a]=$this->fixture();$m=app(MarketingProductionCompiler::class)->compile($p,$r);
        $this->assertSame('use-scenario-hybrid-v1',$m['recipe']);
        $this->assertSame($a->id,$m['scenes'][0]['videos'][0]['asset_id']);
        $this->assertSame('ACME',$m['presentation']['brand']['name']);
        $this->assertTrue($m['audio']['reuse_only']);$this->assertSame(0,$m['budget']['cap_microusd']);
        $this->assertSame([], $m['blockers']);Http::assertNothingSent();
    }
    public function test_revoked_or_cross_project_clip_blocks_instead_of_becoming_still_image():void
    {
        [$p,$r,$a]=$this->fixture();$a->update(['authorized'=>false]);
        $this->assertNotEmpty(app(MarketingProductionCompiler::class)->compile($p,$r)['blockers']);
        $a->update(['authorized'=>true]);$s=$r->document;$s['scenes'][0]['asset_ids']=['unavailable'];$r->document=$s;
        $this->assertNotEmpty(app(MarketingProductionCompiler::class)->compile($p,$r)['blockers']);
    }
    public function test_missing_voice_never_calls_provider_even_when_live_is_enabled():void
    {
        [$p,$r]=$this->fixture();$m=app(MarketingProductionCompiler::class)->compile($p,$r);
        $r->fill(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1])->save();
        $plan=MarketingProductionPlan::create(['project_id'=>$p->id,'revision_id'=>$r->id,'prepared_by'=>$p->owner_id,'request_id'=>\Illuminate\Support\Str::uuid(),'project_version'=>1,'snapshot'=>[],'manifest'=>$m,'fingerprint'=>str_repeat('a',64),'status'=>'budget_confirmed','expires_at'=>now()->addDay()]);
        $task=MarketingProduction::create(['project_id'=>$p->id,'plan_id'=>$plan->id,'owner_id'=>$p->owner_id,'status'=>'preparing']);
        config(['marketing.production.live_audio_enabled'=>true,'marketing.production.live_audio_project'=>$p->id]);
        try{app(MarketingVoiceLibrary::class)->obtain($task,$plan);$this->fail('Must block absent approved voice');}catch(MarketingProductionFailure $e){$this->assertSame('approved_voice_unavailable',$e->reason);}
        Http::assertNothingSent();
    }
    public function test_unknown_recipe_or_bad_layout_is_blocked():void
    {
        [$p,$r]=$this->fixture();$c=$r->context;$c['presentation']['scenes'][0]['layout']='arbitrary_script';$r->context=$c;
        $this->assertNotEmpty(app(MarketingProductionCompiler::class)->compile($p,$r)['blockers']);
        $c['production_recipe']='unknown';$r->context=$c;
        $this->assertNotEmpty(app(MarketingProductionCompiler::class)->compile($p,$r)['blockers']);
    }
    public function test_product_scene_cannot_silently_lose_its_image():void
    {
        [$p,$r]=$this->fixture();$d=$r->document;$d['scenes'][1]['asset_ids']=[];$r->document=$d;
        $this->assertNotEmpty(app(MarketingProductionCompiler::class)->compile($p,$r)['blockers']);
    }
    public function test_graphic_layout_rejects_photos_it_cannot_render():void
    {
        [$p,$r]=$this->fixture();$d=$r->document;$d['scenes'][2]['asset_ids']=$d['scenes'][1]['asset_ids'];$r->document=$d;
        $this->assertNotEmpty(app(MarketingProductionCompiler::class)->compile($p,$r)['blockers']);
    }
    public function test_viewer_copy_uses_storyboard_only_and_staging_removes_director_instructions():void
    {
        [$p,$r]=$this->fixture();$context=$r->context;
        foreach($context['presentation']['scenes'] as &$scene){$scene['heading']='DIRECTOR ONLY';$scene['lines']=['MOVE CAMERA','INSERT OVERLAY','DO NOT DISPLAY'];$scene['body']='Show private production notes.';}unset($scene);
        $r->context=$context;$before=$r->getAttributes();
        $m=app(MarketingProductionCompiler::class)->compile($p,$r);
        $this->assertSame('storyboard-viewer-copy-v1',$m['copy_contract']);
        $this->assertSame('A clear message',$m['scenes'][4]['viewer_copy']['text']);
        $this->assertSame('document.scenes.4.subtitle',$m['scenes'][4]['viewer_copy']['source']);
        $this->assertSame($before,$r->getAttributes());
        $plan=new MarketingProductionPlan(['manifest'=>$m,'snapshot'=>['source'=>['title'=>'A real product'],'revision_number'=>1,'brief'=>[]]]);
        $task=new MarketingProduction(['id'=>(string)\Illuminate\Support\Str::uuid()]);
        // Compiler already verified fixture media; stage uses exact paths without a second fixture decode.
        $directory=app(MarketingProductionWorkspace::class)->stage($task,$plan);
        $input=file_get_contents($directory.'/input.json');$decoded=json_decode($input,true);
        $this->assertStringNotContainsString('DIRECTOR ONLY',$input);$this->assertStringNotContainsString('Show private production notes.',$input);
        $this->assertArrayNotHasKey('visual',$decoded['manifest']['scenes'][0]);$this->assertArrayNotHasKey('material',$decoded['manifest']['scenes'][0]);
        $this->assertArrayNotHasKey('body',$decoded['manifest']['presentation']['scenes'][0]);
        Http::assertNothingSent();
    }
    public function test_exact_credit_authorization_allows_one_voice_request_without_usd_budget_and_never_retries():void
    {
        [$p,$r]=$this->fixture();$r->fill(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1])->save();
        $compiler=app(MarketingProductionCompiler::class);$base=$compiler->compile($p,$r);
        $spec=app(MarketingVoiceLibrary::class)->spec(new MarketingProductionPlan(['manifest'=>$base]));
        config(['marketing.production.credit_voice'=>['enabled'=>true,'id'=>(string)\Illuminate\Support\Str::uuid(),'project'=>$p->id,'revision'=>$r->id,'signature'=>app(MarketingVoiceLibrary::class)->signature($spec),'max_credits'=>100,'expires_at'=>now()->addHour()->toIso8601String()], 'marketing.production.live_audio_enabled'=>true,'marketing.production.live_audio_project'=>$p->id,'marketing.production.elevenlabs_key'=>'fixture-key']);
        $m=$compiler->compile($p,$r);$this->assertFalse($m['audio']['reuse_only']);$this->assertSame(100,$m['budget']['cap_credits']);
        $plan=MarketingProductionPlan::create(['project_id'=>$p->id,'revision_id'=>$r->id,'prepared_by'=>$p->owner_id,'request_id'=>\Illuminate\Support\Str::uuid(),'project_version'=>1,'snapshot'=>[],'manifest'=>$m,'fingerprint'=>str_repeat('a',64),'status'=>'budget_confirmed','confirmed_by'=>$p->owner_id,'confirmed_at'=>now(),'expires_at'=>now()->addDay()]);
        $task=MarketingProduction::create(['project_id'=>$p->id,'plan_id'=>$plan->id,'owner_id'=>$p->owner_id,'status'=>'preparing']);
        Http::fake(['api.elevenlabs.io/*'=>Http::response(['error'=>'rejected'],400)]);
        try{app(MarketingVoiceLibrary::class)->obtain($task,$plan);$this->fail('Expected provider rejection');}catch(MarketingProductionFailure $e){}
        $voice=\App\Models\MarketingVoiceAsset::where('project_id',$p->id)->firstOrFail();$this->assertSame($m['audio']['credit_authorization']['id'],$voice->usage['credit_authorization']['id']);
        try{app(MarketingVoiceLibrary::class)->obtain($task->fresh(),$plan);$this->fail('No second request');}catch(MarketingProductionFailure $e){}
        Http::assertSentCount(1);$this->assertDatabaseCount('marketing_production_budgets',0);
        $this->assertSame(0,$task->fresh()->new_reserved_microusd);
        $d=$r->document;$d['scenes'][0]['voiceover']='Changed text';$r->document=$d;
        $this->assertTrue($compiler->compile($p,$r)['audio']['reuse_only']);
    }
    public function test_credit_profile_rejects_expiry_changed_signature_and_excess_cap():void
    {
        [$p,$r]=$this->fixture();$r->fill(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1])->save();
        $m=app(MarketingProductionCompiler::class)->compile($p,$r);$spec=app(MarketingVoiceLibrary::class)->spec(new MarketingProductionPlan(['manifest'=>$m]));
        $grant=['enabled'=>true,'id'=>(string)\Illuminate\Support\Str::uuid(),'project'=>$p->id,'revision'=>$r->id,'signature'=>app(MarketingVoiceLibrary::class)->signature($spec),'max_credits'=>100,'expires_at'=>now()->addHour()->toIso8601String()];
        $a=app(\App\Services\Marketing\MarketingAudioCreditAuthorization::class);
        foreach([['expires_at'=>now()->subSecond()->toIso8601String()],['signature'=>str_repeat('a',64)],['max_credits'=>101],['revision'=>(string)\Illuminate\Support\Str::uuid()]] as $change){config(['marketing.production.credit_voice'=>array_replace($grant,$change)]);$this->assertNull($a->profile($p,$r,$spec['text']));}
        Http::assertNothingSent();
    }
    public static function creditCharges():array { return [[34,true,false],[101,false,false],[34,false,true]]; }
    #[\PHPUnit\Framework\Attributes\DataProvider('creditCharges')]
    public function test_credit_voice_success_records_actual_cost_and_reuses_without_another_request(int $charge,bool $valid,bool $malformed):void
    {
        [$p,$r]=$this->fixture();$r->fill(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1])->save();
        $compiler=app(MarketingProductionCompiler::class);$base=$compiler->compile($p,$r);$spec=app(MarketingVoiceLibrary::class)->spec(new MarketingProductionPlan(['manifest'=>$base]));
        $grant=['enabled'=>true,'id'=>(string)\Illuminate\Support\Str::uuid(),'project'=>$p->id,'revision'=>$r->id,'signature'=>app(MarketingVoiceLibrary::class)->signature($spec),'max_credits'=>100,'expires_at'=>now()->addHour()->toIso8601String()];
        config(['marketing.production.credit_voice'=>$grant,'marketing.production.live_audio_enabled'=>true,'marketing.production.live_audio_project'=>$p->id,'marketing.production.elevenlabs_key'=>'fixture-key']);
        $m=$compiler->compile($p,$r);$plan=MarketingProductionPlan::create(['project_id'=>$p->id,'revision_id'=>$r->id,'prepared_by'=>$p->owner_id,'request_id'=>\Illuminate\Support\Str::uuid(),'project_version'=>1,'snapshot'=>[],'manifest'=>$m,'fingerprint'=>str_repeat('a',64),'status'=>'budget_confirmed','confirmed_by'=>$p->owner_id,'confirmed_at'=>now(),'expires_at'=>now()->addDay()]);
        $task=MarketingProduction::create(['project_id'=>$p->id,'plan_id'=>$plan->id,'owner_id'=>$p->owner_id,'status'=>'preparing']);
        $chars=mb_str_split($spec['text']);$indexes=array_keys($chars);
        Http::fake(['api.elevenlabs.io/*'=>Http::response($malformed?['audio_base64'=>'bad','alignment'=>[]]:['audio_base64'=>base64_encode('ID3-fixture-valid-bytes'),'alignment'=>['characters'=>$chars,'character_start_times_seconds'=>array_map(fn($i)=>$i*.01,$indexes),'character_end_times_seconds'=>array_map(fn($i)=>($i+1)*.01,$indexes)]],200,['character-cost'=>(string)$charge])]);
        app(MarketingMediaTools::class)->shouldReceive('audioDuration')->andReturn(2.0);
        $lib=app(MarketingVoiceLibrary::class);
        if(!$valid){
            try{$lib->obtain($task,$plan);$this->fail('Unexpected credit usage must stop');}catch(MarketingProductionFailure $e){$this->assertSame('unknown',$e->state);}
            $voice=\App\Models\MarketingVoiceAsset::where('project_id',$p->id)->firstOrFail();$this->assertSame((float)$charge,(float)($voice->usage['character_cost']??0));
            try{$lib->obtain($task->fresh(),$plan);$this->fail('No retry');}catch(MarketingProductionFailure $e){}
            Http::assertSentCount(1);return;
        }
        $voice=$lib->obtain($task,$plan);$again=$lib->obtain($task->fresh(),$plan);
        $this->assertSame($voice->id,$again->id);$this->assertSame('ready',$voice->status);$this->assertSame(34.0,(float)$voice->usage['character_cost']);$this->assertSame($grant['id'],$voice->usage['credit_authorization']['id']);
        $this->assertTrue($task->fresh()->result['voice_request_claimed']);Http::assertSentCount(1);$this->assertDatabaseCount('marketing_production_budgets',0);
    }
}
