<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Concerns\MarketingResearchFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http,Queue};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use App\Models\MarketingRevision;
use App\Services\Marketing\{MarketingMaterialPackage,MarketingMaterialReferences,MarketingMaterialImages,MarketingMaterialImageClient,MarketingWorkflow};

class MarketingWorkspaceVideoTest extends TestCase
{
 use RefreshDatabase,MarketingResearchFixtures;
 private function ready():array{
  Storage::fake('local');Storage::fake('public');$p=$this->researchProject();config(['marketing.workspace_video.enabled'=>true,'marketing.native.api_key'=>'fixture-key']);
  \App\Models\AiConnection::where('provider','openai')->update(['image_model'=>'gpt-image-2','models'=>[['id'=>'gpt-image-2','image'=>true]]]);$p->update(['concepts'=>[['title'=>'用途介绍','video_type'=>'presenter','ratio'=>'9:16']],'selected_concept'=>0,'planning_state'=>['status'=>'confirmed'],'preferences'=>['complete'=>true,'subtitles'=>true,'narration'=>true,'language'=>'English']]);
  $doc=['summary'=>'用途','duration'=>15,'cta'=>'Enquire','scenes'=>MarketingMaterialPackageTest::facts()['scenes'],'director_plan'=>MarketingDirectorContractTest::directorOutput(),'gaps'=>[]];
  $r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>$doc,'context'=>['brief'=>$p->brief,'snapshot'=>$p->snapshot,'concept'=>$p->concepts[0]]]);$doc['director_plan']['source_revision_id']=$r->id;$doc['director_plan']['source_digest']=app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->digest($p,$r);$r->update(['document'=>$doc]);
  $facts=MarketingMaterialPackageTest::facts();$facts['source_revision_id']=$r->id;$facts['source_digest']=app(MarketingMaterialPackage::class)->digest($p,$r);$doc['material_package']=app(MarketingMaterialPackage::class)->build(MarketingMaterialPackageTest::materialOutput(),$facts);$r->update(['document'=>$doc]);$context=$r->context;$context['director_review']=['plan_digest'=>hash('sha256',json_encode($doc['director_plan'],JSON_THROW_ON_ERROR))];$r->update(['context'=>$context]);$p->update(['current_revision_id'=>$r->id]);
  app(MarketingMaterialReferences::class)->save($p->id,$p->owner_id,$p->version,$doc['material_package']['requirements'][0]['id'],(string)Str::uuid(),['permission'=>true],UploadedFile::fake()->image('reference.png'));$p->refresh();$images=app(MarketingMaterialImages::class);$run=$images->quote($p->id,$p->owner_id,$p->version,(string)Str::uuid());$images->authorize($p->id,$p->owner_id,$run->id);$file=UploadedFile::fake()->image('ai.png',864,1536);$this->partialMock(MarketingMaterialImageClient::class)->shouldReceive('generate')->times(3)->andReturn(['bytes'=>file_get_contents($file->getRealPath()),'usage'=>[],'actual_usd'=>.01,'request_id'=>'fixture']);foreach($run->usage['outputs'] as $out){$images->advance($p->id,$p->owner_id,$run->id);$images->review($p->id,$p->owner_id,$run->id,$out['id'],true);}
  return [$p->fresh(),app(\App\Services\Marketing\MarketingClipProduction::class)];
 }
 public function test_quote_is_free_and_replay_safe_and_authorization_requires_exact_consent():void{
  [$p,$service]=$this->ready();$request=(string)Str::uuid();$clip=$service->quote($p->id,$p->owner_id,$p->version,1,$request);$again=$service->quote($p->id,$p->owner_id,$p->version,1,$request);$this->assertSame($clip->id,$again->id);$this->assertSame('quoted',$clip->status);$this->assertSame(202.5,$clip->quote['credits']);$this->assertSame(1,$clip->input['scene_number']);$this->assertTrue($clip->input['payload']['generate_audio']);
  $url='/admin/marketing/workspace/'.$p->id.'/clips/'.$clip->id.'/authorize';$this->postJson($url,['version'=>$p->version])->assertUnprocessable();$this->postJson($url,['version'=>$p->version,'consent'=>1])->assertOk();$this->postJson($url,['version'=>$p->version,'consent'=>1])->assertOk();$this->assertSame('upload_pending',$clip->fresh()->status);Queue::assertPushed(\App\Jobs\ProcessMarketingWorkspaceVideo::class,1);Http::assertNothingSent();
 }
 public function test_changed_asset_blocks_spend_and_status_get_has_no_side_effects():void{
  [$p,$service]=$this->ready();$clip=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$this->getJson('/admin/marketing/workspace/'.$p->id.'/video-status')->assertOk();$this->assertSame('quoted',$clip->fresh()->status);
  $p->assets()->whereKey($clip->input['image']['asset_id'])->update(['authorized'=>false]);$this->postJson('/admin/marketing/workspace/'.$p->id.'/clips/'.$clip->id.'/authorize',['version'=>$p->version,'consent'=>1])->assertConflict();Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_owner_cannot_access_another_projects_clip():void{
  [$p,$service]=$this->ready();$clip=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$other=\App\Models\User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($other)->postJson('/admin/marketing/workspace/'.$p->id.'/clips/'.$clip->id.'/authorize',['version'=>$p->version,'consent'=>1])->assertNotFound();Http::assertNothingSent();
 }
 private function fakeSupplier(bool $unknown=false,int $submitStatus=200):void{
  Http::fake(['files-api.evolink.ai/*'=>Http::response(['success'=>true,'data'=>['file_id'=>'fixture-file','file_url'=>'https://files.evolink.ai/fixture.png','expires_at'=>now()->addDays(3)->toIso8601String()]]),'api.evolink.ai/v1/credits'=>Http::response(['data'=>['user'=>['remaining_credits'=>1000],'token'=>['unlimited_credits'=>true]]]),'api.evolink.ai/v1/videos/generations'=>Http::response($unknown?['unexpected'=>'outcome']:['id'=>'fixture-video','usage'=>['credits_reserved'=>202.5]],$submitStatus),'api.evolink.ai/v1/tasks/*'=>Http::response(['id'=>'fixture-video','status'=>'completed','results'=>['https://cdn.example.com/output.mp4'],'usage'=>['credits_used'=>202.5]])]);
 }
 private function mockDownload():void{
  $this->partialMock(\App\Services\Marketing\MarketingVideoFiles::class)->shouldReceive('download')->once()->andReturnUsing(function($url,$path){Storage::disk('local')->put($path,'verified raw fixture');return ['path'=>$path,'sha256'=>hash('sha256','verified raw fixture'),'duration'=>15,'width'=>720,'height'=>1280];});
 }
 private function fakeRenderedOutput($c):array{$path='marketing-clips/'.$c->id.'/output.mp4';Storage::disk('local')->put($path,'verified output fixture');return ['path'=>$path,'sha256'=>hash('sha256','verified output fixture'),'duration'=>15,'width'=>720,'height'=>1280];}
 public function test_background_pipeline_submits_once_then_reuses_completed_clip():void{
  [$p,$service]=$this->ready();$clip=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$clip->id);$this->fakeSupplier();$this->mockDownload();$this->mock(\App\Services\Marketing\MarketingVideoCompositor::class)->shouldReceive('clip')->once()->andReturnUsing(fn($c)=>$this->fakeRenderedOutput($c));
  foreach(['submit_pending','external_pending','download_pending','render_pending','completed'] as $state){$service->advance($clip->id);$this->assertSame($state,$clip->fresh()->status);}
  $service->advance($clip->id);$reuse=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$this->assertSame($clip->id,$reuse->id);$this->assertDatabaseCount('marketing_video_clips',1);$this->assertSame(202.5,$clip->fresh()->result['provider_usage']['credits_used']);
  $posts=Http::recorded(fn($request)=>$request->method()==='POST'&&$request->url()==='https://api.evolink.ai/v1/videos/generations');$this->assertCount(1,$posts);$this->assertCount(1,$posts->first()[0]['image_urls']);$this->assertTrue($posts->first()[0]['generate_audio']);
 }
 public function test_unknown_submit_never_resubmits_even_with_new_quote_or_retry():void{
  [$p,$service]=$this->ready();$clip=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$clip->id);$this->fakeSupplier(true);$service->advance($clip->id);$service->advance($clip->id);$this->assertSame('unknown',$clip->fresh()->status);$service->advance($clip->id);$same=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$this->assertSame($clip->id,$same->id);$this->postJson('/admin/marketing/workspace/'.$p->id.'/clips/'.$clip->id.'/retry')->assertConflict();$this->assertCount(1,Http::recorded(fn($r)=>$r->url()==='https://api.evolink.ai/v1/videos/generations'));
 }
 public function test_local_render_retry_does_not_repeat_generation():void{
  [$p,$service]=$this->ready();$clip=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$clip->id);$this->fakeSupplier();$this->mockDownload();$renderer=$this->mock(\App\Services\Marketing\MarketingVideoCompositor::class);$renderer->shouldReceive('clip')->once()->andThrow(new \App\Services\Marketing\MarketingProductionFailure('media_processing_failed'));
  for($i=0;$i<5;$i++)$service->advance($clip->id);$this->assertSame('failed',$clip->fresh()->status);$service->retry($p->id,$p->owner_id,$clip->id);$this->assertSame('render_pending',$clip->fresh()->status);$renderer->shouldReceive('clip')->once()->andReturnUsing(fn($c)=>$this->fakeRenderedOutput($c));$service->advance($clip->id);$this->assertSame('completed',$clip->fresh()->status);$this->assertCount(1,Http::recorded(fn($r)=>$r->url()==='https://api.evolink.ai/v1/videos/generations'));
 }
 public function test_expired_quote_cannot_start_or_dispatch():void{
  [$p,$service]=$this->ready();$clip=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$this->travel(31)->minutes();$this->postJson('/admin/marketing/workspace/'.$p->id.'/clips/'.$clip->id.'/authorize',['version'=>$p->version,'consent'=>1])->assertConflict();Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_verified_pilot_is_imported_without_buying_video_again():void{
  [$p,$service]=$this->ready();$source=app(\App\Services\Marketing\MarketingClipSource::class)->build($p,1);$path=Storage::disk('local')->path('pilot-raw.mp4');file_put_contents($path,str_repeat('raw',500));$r=$p->currentRevision;
  $run=\App\Models\MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'single_clip_pilot','status'=>'completed','usage'=>['profile'=>'operator-single-shot-probe-v1','scene_number'=>1,'source_revision'=>$r->id,'source_digest'=>hash('sha256',json_encode([$r->id,$r->document,$p->brief,$p->preferences],JSON_THROW_ON_ERROR)),'source_image'=>$source['input']['image'],'spoken_text'=>$source['input']['spoken_text'],'payload'=>$source['input']['payload'],'provider_task_id'=>'existing-pilot-task','provider_result'=>['usage'=>['credits_used'=>202.5]],'media'=>['path'=>$path,'sha256'=>hash_file('sha256',$path)],'acceptance'=>['speech_matches_ignoring_punctuation'=>true]]]);
  $this->partialMock(\App\Services\Marketing\MarketingVideoFiles::class)->shouldReceive('verify')->once()->andReturn(['sha256'=>hash_file('sha256',$path),'duration'=>15,'width'=>720,'height'=>1280]);
  $a=$service->importPilot($p->id,$p->owner_id,$p->version,$run->id);$b=$service->importPilot($p->id,$p->owner_id,$p->version,$run->id);$this->assertSame($a->id,$b->id);$this->assertSame('render_pending',$a->status);$this->assertSame($run->id,$a->origin_run_id);$this->assertSame(0,$a->quote['estimate_microusd']);$this->assertSame(hash_file('sha256',$path),$a->raw_sha256);Queue::assertPushed(\App\Jobs\ProcessMarketingWorkspaceVideo::class,1);Http::assertNothingSent();
 }
 public function test_film_assembly_and_approval_bind_current_clips_and_hashes():void{
  [$p,$clips]=$this->ready();$c=$clips->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$out=$this->fakeRenderedOutput($c);$c->update(['status'=>'completed','raw_path'=>$out['path'],'raw_sha256'=>$out['sha256'],'output_path'=>$out['path'],'output_sha256'=>$out['sha256']]);
  $films=app(\App\Services\Marketing\MarketingWorkspaceFilm::class);$film=$films->prepare($p->id,$p->owner_id,$p->version,[$c->id]);$again=$films->prepare($p->id,$p->owner_id,$p->version,[$c->id]);$this->assertSame($film->id,$again->id);
  $this->mock(\App\Services\Marketing\MarketingVideoCompositor::class)->shouldReceive('film')->once()->andReturnUsing(function($f){$path='marketing-films/'.$f->id.'/output.mp4';Storage::disk('local')->put($path,'assembled fixture');return ['path'=>$path,'sha256'=>hash('sha256','assembled fixture'),'duration'=>15];});$films->process($film->id);$film->refresh();$this->assertSame('completed',$film->status);
  $url='/admin/marketing/workspace/'.$p->id.'/films/'.$film->id;$this->get($url.'/preview')->assertOk();$this->getJson($url.'/download')->assertConflict();$this->postJson($url.'/approve',['consent'=>1,'sha256'=>str_repeat('a',64)])->assertConflict();$this->postJson($url.'/approve',['consent'=>1,'sha256'=>$film->output_sha256])->assertOk();$this->get($url.'/download')->assertOk();
  $prefs=$p->preferences;$prefs['narration']=false;$p->update(['preferences'=>$prefs]);$this->get($url.'/download')->assertOk();Storage::disk('local')->put($film->output_path,'tampered approved output');$this->getJson($url.'/download')->assertConflict();Http::assertNothingSent();
 }
 public function test_workbench_shows_free_quote_and_polling_does_not_run_jobs():void{
  [$p,$service]=$this->ready();$this->get('/admin/marketing/workspace?project='.$p->id.'&screen=confirmation')->assertOk()->assertSee('逐段制作')->assertSee('查看本段费用');
  $c=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$c->id);Queue::fake();for($i=0;$i<3;$i++)$this->getJson('/admin/marketing/workspace/'.$p->id.'/video-status')->assertOk();$this->assertSame('upload_pending',$c->fresh()->status);Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_changed_source_between_upload_and_submit_blocks_paid_post():void{
  [$p,$service]=$this->ready();$c=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$c->id);$this->fakeSupplier();$service->advance($c->id);$prefs=$p->preferences;$prefs['narration']=false;$p->update(['preferences'=>$prefs]);$service->advance($c->id);$this->assertSame('failed',$c->fresh()->status);$this->assertCount(0,Http::recorded(fn($r)=>$r->url()==='https://api.evolink.ai/v1/videos/generations'));
 }
 public function test_queue_delay_expiry_or_connection_change_never_submits():void{
  [$p,$service]=$this->ready();$c=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$c->id);$this->travel(31)->minutes();$service->advance($c->id);$this->assertSame('failed',$c->fresh()->status);Http::assertNothingSent();
  $this->travelBack();$other=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$other->id);config(['marketing.native.api_key'=>'a-different-connection']);$service->advance($other->id);$this->assertSame('failed',$other->fresh()->status);Http::assertNothingSent();
 }
 public function test_missing_reordered_or_tampered_clips_cannot_assemble():void{
  [$p,$service]=$this->ready();$c=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$out=$this->fakeRenderedOutput($c);$c->update(['status'=>'completed','raw_path'=>$out['path'],'raw_sha256'=>$out['sha256'],'output_path'=>$out['path'],'output_sha256'=>$out['sha256']]);
  $url='/admin/marketing/workspace/'.$p->id.'/films';$this->postJson($url,['version'=>$p->version,'consent'=>1,'clips'=>[(string)Str::uuid()]])->assertUnprocessable();
  $c->update(['scene_number'=>2]);$this->postJson($url,['version'=>$p->version,'consent'=>1,'clips'=>[$c->id]])->assertUnprocessable();$c->update(['scene_number'=>1]);Storage::disk('local')->put($c->raw_path,'changed raw');$this->postJson($url,['version'=>$p->version,'consent'=>1,'clips'=>[$c->id]])->assertConflict();$this->assertDatabaseCount('marketing_video_films',0);Http::assertNothingSent();
 }
 public function test_new_workbench_finished_state_and_old_ui_are_separate():void{
  [$p]=$this->ready();$url='/admin/marketing/workspace?project='.$p->id;$this->get($url.'&screen=finished')->assertOk()->assertSee('还没有完整成片')->assertSee('继续制作片段');
  config(['marketing.workspace_video.enabled'=>false]);$this->get($url.'&screen=confirmation')->assertOk()->assertDontSee('逐段制作，先看效果')->assertSee('本地制作预检');Http::assertNothingSent();
 }
 public function test_explicit_provider_failure_offers_repricing_without_resubmitting():void{
  [$p,$service]=$this->ready();$c=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$c->update(['status'=>'failed','authorized_at'=>now(),'provider_task_id'=>'known-failed-task','error_code'=>'provider_failed','error_message'=>'供应商明确失败','result'=>['submission_claimed'=>now()->toIso8601String()]]);
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=confirmation')->assertOk()->assertSee('重新核价，再确认是否制作');
  $this->postJson('/admin/marketing/workspace/'.$p->id.'/clips/quote',['version'=>$p->version,'scene'=>1,'request_id'=>(string)Str::uuid()])->assertOk()->assertJsonPath('status','quoted');$this->assertDatabaseCount('marketing_video_clips',2);Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_confirmed_submission_rejection_can_be_repriced_but_timeout_stays_unknown():void{
  [$p,$service]=$this->ready();$c=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$service->authorize($p->id,$p->owner_id,$p->version,$c->id);$this->fakeSupplier(false,422);$service->advance($c->id);$service->advance($c->id);$c->refresh();$this->assertSame('failed',$c->status);$this->assertSame('native_request_rejected',$c->error_code);
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=confirmation')->assertOk()->assertSee('重新核价，再确认是否制作');Queue::fake();$new=$service->quote($p->id,$p->owner_id,$p->version,1,(string)Str::uuid());$this->assertNotSame($c->id,$new->id);$this->assertSame('quoted',$new->status);$this->assertNull($new->authorized_at);Queue::assertNothingPushed();$this->assertCount(1,Http::recorded(fn($r)=>$r->url()==='https://api.evolink.ai/v1/videos/generations'));
 }
}
