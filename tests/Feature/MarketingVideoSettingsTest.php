<?php
namespace Tests\Feature;
use App\Models\{MarketingRevision,MarketingStoryboardWorkspace as Workspace,MarketingSceneRevision,MarketingGenerationQuote};
use App\Services\Marketing\{MarketingStoryboardWorkspace,MarketingVideoSettings};
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;
class MarketingVideoSettingsTest extends TestCase {
 use MarketingResearchFixtures;
 protected function setUp():void{parent::setUp();$this->artisan('migrate:fresh')->run();}
 private function story():array{$p=$this->researchProject();$p->update(['preferences'=>array_replace($p->preferences??[],['narration'=>true,'subtitles'=>true])]);$r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>[['visual'=>'Tea garden','voiceover'=>'Original first line','subtitle'=>'Original first caption','duration'=>5],['visual'=>'Tea cup','voiceover'=>'Original second line','subtitle'=>'Original second caption','duration'=>5]]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);return [$p,app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version)];}
 public function test_scene_override_global_default_and_inherit_preserve_original_scripts_and_frozen_data():void{
  [$p,$s]=$this->story();$raw=MarketingSceneRevision::find($s['scenes'][0]['revision_id'])->content;$frozen=$s;$svc=app(MarketingVideoSettings::class);
  $s=$svc->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['field'=>'voice','value'=>'none','scope'=>'scene','scene_id'=>$s['scenes'][0]['scene_id']]);
  $this->assertSame('Original first line',$s['scenes'][0]['voiceover']);$this->assertSame('无口播',$s['video_settings']['scenes'][$s['scenes'][0]['scene_id']]['tags']['voice']['label']);$this->assertSame('',$svc->apply($s['scenes'][0],$p->fresh(),Workspace::where('project_id',$p->id)->first()->profile,$s['scenes'][0]['scene_id'])['voiceover']);$this->assertSame('Original second line',$s['scenes'][1]['voiceover']);
  $s=$svc->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['field'=>'subtitles','value'=>'off','scope'=>'global']);$this->assertSame('Original first caption',$s['scenes'][0]['subtitle']);$this->assertSame('Original second caption',$s['scenes'][1]['subtitle']);$this->assertSame('无字幕',$s['video_settings']['scenes'][$s['scenes'][0]['scene_id']]['tags']['subtitles']['label']);
  $s=$svc->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['field'=>'voice','value'=>'inherit','scope'=>'scene','scene_id'=>$s['scenes'][0]['scene_id']]);$this->assertSame('Original first line',$s['scenes'][0]['voiceover']);$this->assertTrue($s['source_fresh']);
  $this->assertSame($raw,MarketingSceneRevision::find($s['scenes'][0]['revision_id'])->content);$this->assertSame('Original first caption',$frozen['scenes'][0]['subtitle']);$this->assertNull(Workspace::where('project_id',$p->id)->first()->last_confirmation);Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_free_normal_endpoint_is_replay_safe_and_invalid_setting_cannot_save():void{
  [$p,$s]=$this->story();$url=route('marketing.workspace.storyboard.write',[$p->id,'video-settings']);$body=['request_id'=>(string)Str::uuid(),'version'=>$s['story_version'],'command'=>['field'=>'subtitles','value'=>'off','scope'=>'scene','scene_id'=>$s['scenes'][0]['scene_id']]];
  $a=$this->postJson($url,$body)->assertOk()->json('snapshot');$this->postJson($url,$body)->assertOk()->assertJsonPath('snapshot.story_version',$a['story_version']);$this->assertSame(0,MarketingGenerationQuote::count());
  $this->postJson($url,['request_id'=>(string)Str::uuid(),'version'=>$a['story_version'],'command'=>['field'=>'aspect','value'=>'16:9','scope'=>'global']])->assertStatus(422);Http::assertNothingSent();
 }
 public function test_version_owner_and_active_batch_guards_keep_settings_free_and_history_unchanged():void{
  [$p,$s]=$this->story();$raw=MarketingSceneRevision::find($s['scenes'][0]['revision_id'])->getRawOriginal();
  $svc=app(MarketingVideoSettings::class);$cmd=['field'=>'voice','value'=>'none','scope'=>'global'];
  try{$svc->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']+1,$cmd);$this->fail('stale settings saved');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}
  $a=app(\App\Services\Marketing\MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],array_column($s['scenes'],'revision_id'),1100000);
  \App\Models\MarketingStoryboardTask::create(['project_id'=>$p->id,'authorization_id'=>$a['id'],'revision_id'=>$s['scenes'][0]['revision_id'],'signature'=>$s['scenes'][0]['image_signature'],'status'=>'queued','result'=>[]]);
  try{$svc->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$cmd);$this->fail('active batch settings saved');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}
  $this->assertSame($raw,MarketingSceneRevision::find($s['scenes'][0]['revision_id'])->getRawOriginal());Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_contextual_markup_has_no_repeated_summary_and_export_details_are_collapsed():void{
  [$p,$s]=$this->story();$p->update(['selected_concept'=>0,'concepts'=>[['title'=>'Tea story']]]);
  $this->get('/admin/marketing/workspace?project='.$p->id.'&screen=materials')->assertOk()->assertSee('vs-settings-dialog')->assertSee('vs-candidate-tags')->assertDontSee('summary-surface');
  $film=\App\Models\MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>hash('sha256','history'),'status'=>'completed','input'=>['duration'=>10,'scenes'=>[['seconds'=>5,'subtitle'=>'Saved caption','with_audio'=>false]]],'result'=>[]]);
  $before=$film->input;$html=view('marketing.workspace.video-export-info',['project'=>$p,'film'=>$film])->render();$this->assertStringContainsString('<details class="vs-export-details">',$html);$this->assertStringNotContainsString('data-field',$html);$this->assertSame($before,$film->fresh()->input);Http::assertNothingSent();
 } public function test_clip_reuse_cannot_silently_keep_old_burned_subtitles():void{
  [$p,$s]=$this->story();$scene=$s['scenes'][0];$clip=\App\Models\MarketingVideoClip::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'request_id'=>(string)Str::uuid(),'scene_number'=>1,'fingerprint'=>hash('sha256','settings-old-clip'),'status'=>'completed','expires_at'=>now()->addDay(),'input'=>$scene+['payload'=>['model'=>'seedance-1.5-pro','quality'=>'720p','generate_audio'=>true]],'quote'=>[],'connection_fingerprint'=>'fixture','raw_path'=>'fixture.mp4','raw_sha256'=>str_repeat('a',64),'output_path'=>'fixture-out.mp4','output_sha256'=>str_repeat('b',64)]);
  $this->mock(\App\Services\Marketing\MarketingStoryboardProduction::class)->shouldReceive('assertClip')->andReturn($p);$this->mock(\App\Services\Marketing\MarketingVideoFiles::class)->shouldReceive('file')->andReturn('/fixture');$scene['subtitle']='';
  try{app(\App\Services\Marketing\MarketingStoryboardBatchApproval::class)->clip($p->id,$p->owner_id,$scene,$clip->id,'seedance-1.5-pro');$this->fail('old burned subtitle clip reused');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}Http::assertNothingSent();
 } public function test_handoff_masks_production_only_while_edit_snapshot_keeps_authored_text():void{
  [$p,$s]=$this->story();$service=app(MarketingVideoSettings::class);$s=$service->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['field'=>'voice','value'=>'none','scope'=>'global']);$s=$service->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['field'=>'subtitles','value'=>'off','scope'=>'global']);
  $this->assertSame('Original first line',$s['scenes'][0]['voiceover']);$this->assertSame('Original first caption',$s['scenes'][0]['subtitle']);
  $ready=$s;$ready['readiness']=true;$this->partialMock(MarketingStoryboardWorkspace::class)->shouldReceive('snapshot')->with($p->id,$p->owner_id)->andReturn($ready)->getMock()->shouldReceive('project')->with($p->id,$p->owner_id)->andReturn($p->fresh());
  $h=app(\App\Services\Marketing\MarketingStoryboardHandoff::class)->build($p->id,$p->owner_id,$s['story_id']);$this->assertSame('',$h['scene_map'][0]['voiceover']);$this->assertSame('',$h['scene_map'][0]['subtitle']);$this->assertSame('',$h['director_plan']['shots'][0]['design']['voiceover']??'');Http::assertNothingSent();Queue::assertNothingPushed();
 } public function test_disabled_speech_film_uses_same_production_digest_for_current_version_checks():void{
  [$p,$s]=$this->story();$svc=app(MarketingVideoSettings::class);$s=$svc->save($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],['field'=>'voice','value'=>'none','scope'=>'global']);$profile=Workspace::where('project_id',$p->id)->first()->profile;$project=$p->fresh();$production=array_map(fn($scene)=>$svc->apply($scene,$project,$profile,$scene['scene_id']),$s['scenes']);
  $film=\App\Models\MarketingVideoFilm::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'revision_id'=>$p->current_revision_id,'fingerprint'=>hash('sha256','silent-film'),'status'=>'completed','input'=>['version'=>\App\Services\Marketing\MarketingStoryboardProduction::FILM,'story_id'=>$s['story_id'],'story_version'=>$s['story_version'],'readiness_digest'=>hash('sha256',json_encode($production,JSON_THROW_ON_ERROR)),'scenes'=>[]],'result'=>[]]);
  $this->assertTrue(app(\App\Services\Marketing\MarketingStoryboardVersions::class)->filmBinding($film,$s)['current']);$this->assertSame($p->id,app(\App\Services\Marketing\MarketingStoryboardProduction::class)->assertFilmCurrent($film)->id);Http::assertNothingSent();
 }}
