<?php
namespace Tests\Feature;
use App\Models\{User,MarketingProject,MarketingAsset,MarketingVideoClip,MarketingRevision};
use App\Services\Marketing\{MarketingMediaTools,MarketingVideoFiles,MarketingLocalSceneRenderer,MarketingSceneExecutionPolicy};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingLocalSceneRendererTest extends TestCase
{
 use RefreshDatabase, \Tests\Concerns\MarketingResearchFixtures;
 private function clip(string $route):MarketingVideoClip{
  Http::preventStrayRequests();Storage::fake('local');$u=User::factory()->create(['is_admin'=>true]);$p=MarketingProject::create(['owner_id'=>$u->id,'name'=>'local fixture','version'=>1,'status'=>'draft','source_type'=>'topic','snapshot'=>[],'brief'=>[]]);
  $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aJ1sAAAAASUVORK5CYII=');Storage::disk('local')->put('fixture.png',$bytes);$a=MarketingAsset::create(['project_id'=>$p->id,'name'=>'fixture','path'=>'fixture.png','mime'=>'image/png','size'=>strlen($bytes),'authorized'=>true]);
  $revision=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$u->id,'number'=>1,'document'=>[],'context'=>[]]);
  return MarketingVideoClip::create(['revision_id'=>$revision->id,'quote'=>[],'connection_fingerprint'=>'fixture','expires_at'=>now()->addHour(),'project_id'=>$p->id,'owner_id'=>$u->id,'request_id'=>(string)Str::uuid(),'scene_number'=>1,'fingerprint'=>hash('sha256',(string)Str::uuid()),'status'=>'local_pending','input'=>['seconds'=>9,'image'=>['asset_id'=>$a->id,'sha256'=>hash('sha256',$bytes)],'payload'=>['generate_audio'=>false],'configuration'=>['local'=>true,'execution_policy'=>['version'=>MarketingSceneExecutionPolicy::VERSION,'route'=>$route]]]]);
 }
 public function test_local_render_has_owned_recipe_and_cached_result_without_provider_calls():void{
  $c=$this->clip('source_locked');$calls=0;$this->mock(MarketingMediaTools::class,function($m)use(&$calls){$m->shouldReceive('run')->once()->andReturnUsing(function($args)use(&$calls){$calls++;$this->assertContains('-n',$args);$this->assertContains('-an',$args);$this->assertContains('scale=720:1280:flags=lanczos,setsar=1',$args);file_put_contents(end($args),'fake verified video');return '';});});
  $this->partialMock(MarketingVideoFiles::class,function($m){$m->shouldReceive('verify')->once()->andReturn(['sha256'=>hash('sha256','fake verified video'),'duration'=>9,'width'=>720,'height'=>1280]);});
  $r=app(MarketingLocalSceneRenderer::class);$a=$r->render($c);$b=$r->render($c);$this->assertSame($a,$b);$this->assertSame(1,$calls);$this->assertSame(0,$a['external_requests']);Http::assertNothingSent();
 }
 public function test_changed_source_stops_before_render_or_network():void{$c=$this->clip('conservative_motion');Storage::disk('local')->put('fixture.png','changed');$this->mock(MarketingMediaTools::class,fn($m)=>$m->shouldNotReceive('run'));$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);try{app(MarketingLocalSceneRenderer::class)->render($c);}finally{Http::assertNothingSent();}}
 public function test_unknown_recipe_stops_before_render_or_network():void{$c=$this->clip('arbitrary-command');$this->mock(MarketingMediaTools::class,fn($m)=>$m->shouldNotReceive('run'));$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);try{app(MarketingLocalSceneRenderer::class)->render($c);}finally{Http::assertNothingSent();}}
 public function test_zero_supplier_local_plan_uses_existing_quote_worker_and_preserves_source():void{
  $p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>[['visual'=>'Green tea tin packaging','duration'=>9,'voiceover'=>'','subtitle'=>'Tea']]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
  $ws=app(\App\Services\Marketing\MarketingStoryboardWorkspace::class);$s=$ws->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);
  $ref=app(\App\Services\Marketing\MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,\Illuminate\Http\UploadedFile::fake()->image('tin.png'),true);
  $scene=$s['scenes'][0];$old=\App\Models\MarketingSceneRevision::findOrFail($scene['revision_id']);$content=$old->content;$content['asset']=$ref;$content['readiness']='qa_passed';$content['material_plan']=['mode'=>'single_image','roles'=>['start_frame'],'reason'=>'保留包装','fidelity'=>'exact_brand','motion_kind'=>'camera_only'];$content['frames']['start_frame']=array_replace($content['frames']['start_frame'],['asset'=>$ref,'readiness'=>'qa_passed']);
  $new=\App\Models\MarketingSceneRevision::create(['scene_id'=>$old->scene_id,'parent_id'=>$old->id,'content'=>$content,'signature'=>$old->signature]);\App\Models\MarketingScene::findOrFail($old->scene_id)->update(['current_revision_id'=>$new->id]);
  $s=$ws->snapshot($p->id,$p->owner_id);app(\App\Services\Marketing\MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);
  config(['marketing.billing.enabled'=>true,'marketing.production.local_scene_rate'=>['cap_microusd'=>0,'version'=>'fake-included','pricing_verified_at'=>'2026-10-24','valid_until'=>now()->addDay()->toIso8601String()],'marketing.production.speed_ffmpeg_binary'=>__FILE__]);
  $plan=app(\App\Services\Marketing\MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);$this->assertEmpty($plan->manifest['blockers'],json_encode($plan->manifest['blockers']));$this->assertSame(0,$plan->manifest['budget']['total_posts']);$this->assertSame(0,$plan->manifest['budget']['cap_microusd']);
  $billing=app(\App\Services\Marketing\MarketingGenerationQuotes::class);$q=$billing->quote($p->id,$p->owner_id,$s['story_version'],'start-production',['plan_id'=>$plan->id]);$billing->claim($p->id,$p->owner_id,$q->id,true);$q->refresh();$billing->activate($q);config(['marketing.billing.execution_quote_id'=>$q->id]);
  $this->partialMock(MarketingLocalSceneRenderer::class,function($m){$m->shouldReceive('execute')->once()->andReturnUsing(function($task){$path='fixture/local-raw.mp4';Storage::disk('local')->put($path,'local fake');return ['path'=>$path,'sha256'=>hash('sha256','local fake'),'duration'=>9];});});
  $this->partialMock(\App\Services\Marketing\MarketingVideoCompositor::class,function($m){$m->shouldReceive('storyboardClip')->once()->andReturnUsing(function($clip){return ['path'=>$clip->raw_path,'sha256'=>$clip->raw_sha256,'editing_source'=>['path'=>$clip->raw_path,'sha256'=>$clip->raw_sha256]];});$m->shouldReceive('film')->once()->andReturnUsing(function($film){$path='fixture/local-film.mp4';Storage::disk('local')->put($path,'local film');return ['path'=>$path,'sha256'=>hash('sha256','local film'),'duration'=>9];});});
  $svc=app(\App\Services\Marketing\MarketingStoryboardProduction::class);$run=$svc->start($p->id,$p->owner_id,$s['story_version'],$plan->id,true);for($step=0;$step<8&&$run->fresh()->status!=='completed';$step++)$svc->advance($run->id);
  $this->assertSame('completed',$run->fresh()->status);$this->assertSame($run->id,$svc->start($p->id,$p->owner_id,$s['story_version'],$plan->id,true)->id);$this->assertSame(0,\App\Models\MarketingStoryboardProviderCall::where('project_id',$p->id)->count());$this->assertSame($new->id,$ws->snapshot($p->id,$p->owner_id)['scenes'][0]['revision_id']);Http::assertNothingSent();
 }
}
