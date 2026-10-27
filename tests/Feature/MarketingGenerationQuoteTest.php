<?php
namespace Tests\Feature;
use App\Models\{MarketingRevision,MarketingScene,MarketingSceneRevision,MarketingStoryboardWorkspace as Workspace,MarketingVideoClip,MarketingVideoFilm,MarketingStoryboardProviderCall as Call,AiConnection};
use App\Services\Marketing\{MarketingStoryboardUpload,MarketingStoryboardHandoff,MarketingProductionPreparation,MarketingStoryboardProduction,MarketingVideoFiles,MarketingVideoCompositor,MarketingEvolinkAdapter};
use Illuminate\Http\UploadedFile;
use App\Services\Marketing\{MarketingStoryboardWorkspace,MarketingGenerationQuotes};
use Tests\Concerns\MarketingResearchFixtures;
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;
class MarketingGenerationQuoteTest extends TestCase
{
    use MarketingResearchFixtures;
    protected function setUp():void {parent::setUp();$this->artisan('migrate:fresh')->run();config(['marketing.billing.enabled'=>true]);}
    private function story():array {
        $p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>[['title'=>'Lamp','visual'=>'Desk lamp','duration'=>5,'voiceover'=>'','subtitle'=>'']]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
        $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
        return [$p,$s];
    }
    public function test_new_project_can_quote_confirm_once_and_replay_without_a_pilot_file():void {
        [$p,$s]=$this->story();$svc=app(MarketingGenerationQuotes::class);
        $payload=['command'=>['type'=>'edit','scene_id'=>$s['scenes'][0]['scene_id'],'mode'=>'ai','intent'=>'Warmer lighting','update_scope'=>['director'=>true,'images'=>true]]];
        $q=$svc->quote($p->id,$p->owner_id,$s['story_version'],'changes',$payload);
        $this->assertGreaterThan(0,$q->cap_microusd);$this->assertSame('quoted',$q->status);Http::assertNothingSent();Queue::assertNothingPushed();
        $claimed=$svc->claim($p->id,$p->owner_id,$q->id,true);$this->assertSame($payload,$claimed['quote']->payload);
        $svc->finish($claimed['quote'],['ok'=>true]);
        $this->assertSame(['ok'=>true],$svc->claim($p->id,$p->owner_id,$q->id,true)['replay']);
        $this->assertSame(1,\App\Models\MarketingGenerationQuote::count());Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_quote_does_not_authorize_a_provider_and_stale_story_cannot_be_claimed():void {
        [$p,$s]=$this->story();$svc=app(MarketingGenerationQuotes::class);$q=$svc->quote($p->id,$p->owner_id,$s['story_version'],'material-plan-preview',[]);
        $this->assertFalse($svc->activateProject($p->id));
        Workspace::where('project_id',$p->id)->increment('version');
        try{$svc->claim($p->id,$p->owner_id,$q->id,true);$this->fail('stale quote accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_candidate_image_only_quote_confirmation_queues_once_and_finishes_with_fake_images():void {
        [$p,$s]=$this->story();Queue::fake();Http::preventStrayRequests();
        AiConnection::updateOrCreate(['provider'=>'openai'],['enabled'=>true,'verified_at'=>now(),'api_key'=>'fixture','models'=>[['id'=>'gpt-5.6-luna']],'version'=>1]);
        $ref=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('old.png'),true);
        $scene=$s['scenes'][0];$old=MarketingSceneRevision::findOrFail($scene['revision_id']);$c=$old->content;
        $c['frames']['start_frame']['candidate_asset']=$ref;$c['frames']['start_frame']['qa']=['status'=>'revise'];
        $rev=MarketingSceneRevision::create(['scene_id'=>$old->scene_id,'parent_id'=>$old->id,'content'=>$c,'signature'=>$old->signature]);MarketingScene::findOrFail($old->scene_id)->update(['current_revision_id'=>$rev->id]);
        $this->actingAs(\App\Models\User::findOrFail($p->owner_id));$api=route('marketing.workspace.storyboard',$p->id);
        $payload=['command'=>['type'=>'edit','mode'=>'ai','scene_id'=>$scene['scene_id'],'intent'=>'Remove overlay text only','update_scope'=>['images'=>true,'director'=>false,'frames'=>['start_frame']]]];
        $quote=$this->postJson($api.'/billing-quote',['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'operation'=>'changes','payload'=>$payload])->assertOk()->json('quote');
        $confirm=['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'quote_id'=>$quote['id'],'consent'=>true];
        $out=$this->postJson($api.'/billing-confirm',$confirm)->assertOk()->json();
        $this->assertCount(1,$out['preparation']['tasks']);
        $this->postJson($api.'/billing-confirm',$confirm)->assertOk()->assertJsonPath('preparation.tasks',$out['preparation']['tasks']);
        Queue::assertPushed(\App\Jobs\ProcessMarketingStoryboardPreparation::class,1);
        $new=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('new.png'),true);
        $images=0;app()->instance('marketing.storyboard.image_transport',function()use(&$images,$new){$images++;return ['asset'=>$new,'actual_microusd'=>10];});
        app()->instance('marketing.storyboard.qa_transport',fn()=>['status'=>'pass']);
        $prep=app(\App\Services\Marketing\MarketingStoryboardPreparation::class);$prep->process($out['preparation']['tasks'][0]);$prep->process($out['preparation']['tasks'][0]);
        $this->assertSame(1,$images);$this->assertSame('ready',\App\Models\MarketingStoryboardTask::findOrFail($out['preparation']['tasks'][0])->status);
        $this->assertSame($ref['sha256'],MarketingSceneRevision::findOrFail($rev->id)->content['frames']['start_frame']['candidate_asset']['sha256']);
        Http::assertNothingSent();
    }
 private function readyProduction(bool $unknownUpload=false):array {
  $p=$this->researchProject();$doc=[];foreach([5,6,11,5] as $n=>$seconds)$doc[]=['visual'=>'Warehouse container shot '.($n+1),'duration'=>$seconds,'voiceover'=>'Approved spoken line '.($n+1),'subtitle'=>'Approved subtitle '.($n+1)];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>$doc],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
  $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$ref=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('frame.png'),true);
  $w=Workspace::where('project_id',$p->id)->firstOrFail();$profile=$w->profile;$profile['voice']=['mode'=>'auto'];$w->update(['profile'=>$profile]);
  foreach($s['scenes'] as $i=>$scene){$old=MarketingSceneRevision::findOrFail($scene['revision_id']);$c=$old->content;$c['presenter_required']=false;$c['asset']=$ref;$c['readiness']='qa_passed';$c['director']['continuity']=str_repeat('Keep the warehouse and container unchanged. ',75);
   $pair=in_array($i,[1,2],true);$c['material_plan']=['mode'=>$pair?'first_last_frames':'single_image','roles'=>$pair?['start_frame','end_frame']:['start_frame'],'reason'=>'fixture'];
   foreach($c['material_plan']['roles'] as $role){$c['frames'][$role]['asset']=$ref;$c['frames'][$role]['readiness']='qa_passed';$c['frames'][$role]['required']=true;$c['frames'][$role]['person_presence']='absent';}
   $next=MarketingSceneRevision::create(['scene_id'=>$old->scene_id,'parent_id'=>$old->id,'content'=>$c,'signature'=>$old->signature]);MarketingScene::findOrFail($old->scene_id)->update(['current_revision_id'=>$next->id]);
  }
  $s=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);app(MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
  $scene=$s['scenes'][1];$input=$scene+['image'=>$ref,'opening_asset'=>$ref,'voice_asset'=>null,'payload'=>['model'=>'seedance-1.5-pro','quality'=>'720p','generate_audio'=>true],'configuration'=>['audio'=>['route'=>'native']]];
  $existing=MarketingVideoClip::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$r->id,'request_id'=>(string)Str::uuid(),'scene_number'=>2,'fingerprint'=>hash('sha256','saved-second-clip'),'status'=>'completed','expires_at'=>now()->addDay(),'input'=>$input,'quote'=>[],'connection_fingerprint'=>'fixture','raw_path'=>'fixture/old-raw.mp4','raw_sha256'=>str_repeat('a',64),'output_path'=>'fixture/old-output.mp4','output_sha256'=>str_repeat('b',64)]);
  config(['marketing.native.api_key'=>'fixture']);
  $bounds=[];foreach($s['scenes'] as $i=>$scene)$bounds[$scene['scene_id']]=['digest'=>hash('sha256',json_encode($scene,JSON_THROW_ON_ERROR)),'video_cap_microusd'=>[300000,400000,650000,300000][$i],'reuse_clip'=>$i===1?$existing->id:null];
  config(['marketing.native.api_key'=>'fixture','marketing.storyboard.live_grant'=>[],'marketing.storyboard.economy_trial'=>[],'marketing.storyboard.batch_approval'=>[]]);
  AiConnection::updateOrCreate(['provider'=>'openai'],['enabled'=>true,'verified_at'=>now(),'api_key'=>'fixture','models'=>[['id'=>'gpt-5.6-luna']],'version'=>1]);
  $this->mock(MarketingVideoFiles::class)->shouldReceive('file')->andReturn('/fixture/file.mp4')->getMock()->shouldReceive('download')->andReturn(['path'=>'fixture/new-raw.mp4','sha256'=>str_repeat('c',64)]);
  $this->mock(MarketingVideoCompositor::class)->shouldReceive('storyboardClip')->andReturn(['path'=>'fixture/new-output.mp4','sha256'=>str_repeat('d',64),'editing_source'=>['path'=>'fixture/new-raw.mp4','sha256'=>str_repeat('c',64)]])->getMock()->shouldReceive('film')->andReturn(['path'=>'fixture/new-film.mp4','sha256'=>str_repeat('e',64)]);
  $this->mock(\App\Services\AI\ProviderTransport::class)->shouldReceive('json')->andReturn(['id'=>'fake-prompt','status'=>'completed','usage'=>['input_tokens'=>1000,'output_tokens'=>100],'output'=>[['content'=>[['type'=>'output_text','text'=>'{"direction":"Keep the same warehouse and blue container. Follow the supplied timed director actions and match every required reference frame."}']]]]]);
  $credits=0;$videos=0;Http::fake(['https://api.evolink.ai/v1/models'=>Http::response(['data'=>[['id'=>'seedance-1.5-pro']]]),'https://api.evolink.ai/v1/credits'=>function()use(&$credits,$unknownUpload){$credits++;return Http::response(['data'=>['user'=>['remaining_credits'=>10000,'used_credits'=>10],'token'=>['remaining_credits'=>10000,'used_credits'=>$unknownUpload&&$credits>=3?11:10,'unlimited_credits'=>true]]]);},'https://files-api.evolink.ai/api/v1/files/upload/stream'=>Http::response(['success'=>true,'data'=>['file_id'=>'fake-upload','file_url'=>'https://files.evolink.ai/fake.png','expires_at'=>now()->addDay()->toIso8601String()]]),'https://api.evolink.ai/v1/videos/generations'=>function($q)use(&$videos){return Http::response(['id'=>'fake-video-'.$q['duration'].'-'.(++$videos),'usage'=>['credits_reserved'=>$q['duration']*3.5568]]);},'https://api.evolink.ai/v1/tasks/*'=>fn($q)=>Http::response(['id'=>basename($q->url()),'status'=>'completed','usage'=>['credits_used'=>(int)substr(basename($q->url()),11)*3.5568],'results'=>['https://files.evolink.ai/fake.mp4']])]);Queue::fake();return [$p,$s,$existing];
 }
    public function test_normal_page_quote_confirmation_and_worker_complete_with_fake_suppliers_and_no_pilot():void {
        [$p,$s,$old]=$this->readyProduction();
        $plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $this->assertEmpty($plan->manifest['blockers'],json_encode($plan->manifest['blockers'],JSON_UNESCAPED_UNICODE));
        $api=route('marketing.workspace.storyboard',$p->id);$this->actingAs(\App\Models\User::findOrFail($p->owner_id));
        $quoted=$this->postJson($api.'/billing-quote',['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'operation'=>'start-production','payload'=>['plan_id'=>$plan->id]])->assertOk()->json('quote');
        $this->assertSame(number_format($plan->manifest['budget']['cap_microusd']/1000000,2,'.',''),$quoted['amount']);
        $this->assertSame(0,\App\Models\MarketingProduction::count());
        $confirm=['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'quote_id'=>$quoted['id'],'consent'=>true];
        $run=$this->postJson($api.'/billing-confirm',$confirm)->assertOk()->json('id');
        $this->postJson($api.'/billing-confirm',$confirm)->assertOk()->assertJsonPath('id',$run);
        $this->assertSame(1,\App\Models\MarketingProduction::count());
        config(['marketing.billing.execution_quote_id'=>null,'marketing.storyboard.live_grant'=>[]]);
        $svc=app(MarketingStoryboardProduction::class);for($i=0;$i<35;$i++)$svc->advance($run);
        $state=$svc->state($p->id,$p->owner_id,$run);$this->assertSame('completed',$state['status'],json_encode($state));
        $this->assertSame('completed',$old->fresh()->status);$this->assertSame(str_repeat('b',64),$old->fresh()->output_sha256);
        $calls=Call::where('grant_reference','quote:'.$quoted['id'])->get();$this->assertNotEmpty($calls);
        $this->assertLessThanOrEqual($plan->manifest['budget']['cap_microusd'],$calls->sum('actual_microusd'));
        $count=$calls->count();$svc->advance($run);$this->assertSame($count,Call::where('grant_reference','quote:'.$quoted['id'])->count());
    }
    private function failedPaidProduction():array {
        [$p,$s]=$this->readyProduction();$svc=app(MarketingStoryboardProduction::class);$plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
        $quotes=app(MarketingGenerationQuotes::class);$q=$quotes->quote($p->id,$p->owner_id,$s['story_version'],'start-production',['plan_id'=>$plan->id]);$quotes->claim($p->id,$p->owner_id,$q->id,true);$q->refresh();$quotes->activate($q);config(['marketing.billing.execution_quote_id'=>$q->id]);$run=$svc->start($p->id,$p->owner_id,$s['story_version'],$plan->id,true);$quotes->finish($q,['id'=>$run->id]);
        $svc->advance($run->id);$svc->advance($run->id);$svc->advance($run->id);$run->refresh();$clip=MarketingVideoClip::findOrFail($run->result['clip_ids'][0]);$call=Call::findOrFail($clip->result['video_call_id']);
        app(\App\Services\Marketing\MarketingStoryboardProvider::class)->completeVideo($call->id,$clip->provider_task_id,['provider_status'=>'failed','results'=>[],'actual_microusd'=>10000]);$clip->update(['status'=>'unknown']);$run->update(['status'=>'blocked']);return [$p,$s,$run,$clip,$q];
    }
    public function test_known_failed_paid_attempt_offers_one_quote_and_decline_never_submits():void {
        [$p,$s,$run,$clip]=$this->failedPaidProduction();$recovery=app(\App\Services\Marketing\MarketingProductionRecovery::class);$count=Call::count();$out=$recovery->progress($p->id,$p->owner_id,$run->id);$this->assertSame('approval',$out['mode']);$this->assertSame('0.01',$out['quote']['previous_actual']);$this->assertGreaterThan(0,(float)$out['quote']['additional_limit']);$this->assertSame($out['quote']['id'],$recovery->progress($p->id,$p->owner_id,$run->id)['quote']['id']);
        $recovery->decline($p->id,$p->owner_id,$out['quote']['id']);$this->assertSame('declined',$recovery->progress($p->id,$p->owner_id,$run->id)['mode']);$this->assertSame($count,Call::count());$this->assertSame('blocked',$run->fresh()->status);
    }
    public function test_unknown_charge_never_offers_paid_retry_or_creates_quote():void {
        [$p,$s,$run,$clip]=$this->failedPaidProduction();Call::whereKey($clip->result['video_call_id'])->update(['status'=>'unknown','actual_microusd'=>null]);$count=\App\Models\MarketingGenerationQuote::count();$out=app(\App\Services\Marketing\MarketingProductionRecovery::class)->progress($p->id,$p->owner_id,$run->id);$this->assertSame('platform',$out['mode']);$this->assertSame($count,\App\Models\MarketingGenerationQuote::count());
    }
    public function test_explicit_retry_approval_reuses_parent_run_and_completes_once():void {
        [$p,$s,$run,$clip]=$this->failedPaidProduction();$recovery=app(\App\Services\Marketing\MarketingProductionRecovery::class);$offer=$recovery->progress($p->id,$p->owner_id,$run->id);$this->actingAs(\App\Models\User::findOrFail($p->owner_id));$api=route('marketing.workspace.storyboard',$p->id);$body=['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'quote_id'=>$offer['quote']['id'],'consent'=>true];
        $this->postJson($api.'/billing-confirm',$body)->assertOk()->assertJsonPath('id',$run->id);$count=MarketingVideoClip::count();$this->postJson($api.'/billing-confirm',$body)->assertOk()->assertJsonPath('id',$run->id);$this->assertSame($count,MarketingVideoClip::count());$this->assertSame(1,\App\Models\MarketingProduction::count());$this->assertNotContains($clip->id,$run->fresh()->result['clip_ids']);$this->assertNotNull($clip->fresh());
        $svc=app(MarketingStoryboardProduction::class);for($i=0;$i<40&&$run->fresh()->status!=='completed';$i++)$svc->advance($run->id);$this->assertSame('completed',$run->fresh()->status);$calls=Call::count();$svc->advance($run->id);$this->assertSame($calls,Call::count());$this->assertFalse(Call::where('status','unknown')->exists());$settlement=app(MarketingProductionPreparation::class)->storyboardPreview($p->id,$p->owner_id)['settlement'];$this->assertSame(Call::sum('actual_microusd'),$settlement['actual_microusd']);$this->assertTrue($settlement['known']);
    }
    public function test_unsubmitted_original_work_is_recovered_without_an_extra_quote():void {
        [$p,$s]=$this->readyProduction();$svc=app(MarketingStoryboardProduction::class);$plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);$quotes=app(MarketingGenerationQuotes::class);$q=$quotes->quote($p->id,$p->owner_id,$s['story_version'],'start-production',['plan_id'=>$plan->id]);$quotes->claim($p->id,$p->owner_id,$q->id,true);$q->refresh();$quotes->activate($q);config(['marketing.billing.execution_quote_id'=>$q->id]);$run=$svc->start($p->id,$p->owner_id,$s['story_version'],$plan->id,true);$quotes->finish($q,['id'=>$run->id]);$run->update(['status'=>'blocked']);$count=\App\Models\MarketingGenerationQuote::count();
        $out=app(\App\Services\Marketing\MarketingProductionRecovery::class)->progress($p->id,$p->owner_id,$run->id);$this->assertSame('recovering',$out['mode']);$this->assertSame('preparing',$run->fresh()->status);$this->assertSame($count,\App\Models\MarketingGenerationQuote::count());$this->assertSame(0,Call::count());$recovery=app(\App\Services\Marketing\MarketingProductionRecovery::class);$run->refresh()->update(['status'=>'blocked']);$this->assertSame('recovering',$recovery->progress($p->id,$p->owner_id,$run->id)['mode']);$run->refresh()->update(['status'=>'blocked']);$this->assertSame('platform',$recovery->progress($p->id,$p->owner_id,$run->id)['mode']);$this->assertSame(2,$run->fresh()->result['safe_recoveries']);$this->assertSame(0,Call::count());
    }
    public function test_missing_consent_changed_price_and_included_service_limit_stop_without_transport():void {
        [$p,$s]=$this->story();$svc=app(MarketingGenerationQuotes::class);$q=$svc->quote($p->id,$p->owner_id,$s['story_version'],'material-plan-preview',[]);
        foreach([false,true] as $consent){if($consent)config(['marketing.billing.pricing_verified_at'=>'changed']);try{$svc->claim($p->id,$p->owner_id,$q->id,$consent);$this->fail('invalid confirmation accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame($consent?409:422,$e->getStatusCode());}}
        config(['marketing.billing.included_daily_microusd'=>10000]);try{$svc->quote($p->id,$p->owner_id,$s['story_version'],'material-plan-preview',[]);$this->fail('unbounded service accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(429,$e->getStatusCode());}
        Http::assertNothingSent();Queue::assertNothingPushed();
    }
    public function test_unknown_fee_keeps_result_and_stops_next_call_and_expired_quotes_fail_closed():void {
        [$p,$s]=$this->story();$svc=app(MarketingGenerationQuotes::class);
        $q=$svc->quote($p->id,$p->owner_id,$s['story_version'],'changes',['command'=>['type'=>'edit','mode'=>'ai','scene_id'=>$s['scenes'][0]['scene_id'],'intent'=>'warm lamp','update_scope'=>['director'=>true,'images'=>true]]]);
        $q=$svc->claim($p->id,$p->owner_id,$q->id,true)['quote'];config(['marketing.billing.execution_quote_id'=>$q->id]);$svc->activate($q);
        $a=app(\App\Services\Marketing\MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],array_column($s['scenes'],'revision_id'),$q->cap_microusd);
        $provider=app(\App\Services\Marketing\MarketingStoryboardProvider::class);$sent=0;
        for($i=0;$i<2;$i++){try{$provider->call('semantic',$p->id,$a['id'],['revision_id'=>$s['scenes'][0]['revision_id'],'prompt'=>'fixture-'.$i],function()use(&$sent){$sent++;return ['output'=>'retained result'];});$this->fail('unknown fee accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}}
        $this->assertSame(1,$sent);$this->assertSame('retained result',Call::firstOrFail()->result['output']);$this->assertSame('费用待核对，已暂停',$svc->receipt($p->id,$p->owner_id)['status']);
        $q->update(['expires_at'=>now()->subSecond()]);$this->assertFalse($provider->allowed('semantic',$p->id));
        Http::assertNothingSent();
    }
}
