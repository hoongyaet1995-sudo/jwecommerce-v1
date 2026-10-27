<?php
namespace Tests\Feature;
use App\Services\Marketing\{MarketingProductionPreparation,MarketingUploadCheckpoint};
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
class MarketingUploadCheckpointTest extends MarketingStoryboardTrialScopeTest {
 private function fixture():array {
  [$p,$s]=$this->sample();$plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$s['scenes'][1]['scene_id']);
  config(['marketing.native.api_key'=>'fixture','marketing.production.upload_checkpoint'=>['project'=>$p->id,'scene'=>$s['scenes'][1]['scene_id'],'reference'=>'test-upload','expires_at'=>now()->addHour()->toIso8601String(),'cap_microusd'=>1500000,'max_posts'=>3,'credit_usd'=>10/650]]);
  return [$p,$plan];
 }
 public function test_one_upload_then_pause_and_duplicate_reuses():void {
  [$p,$plan]=$this->fixture();$credits=['data'=>['user'=>['remaining_credits'=>100,'used_credits'=>20],'token'=>['used_credits'=>20]]];
  Http::fake(['https://api.evolink.ai/v1/credits'=>Http::response($credits),'https://files-api.evolink.ai/api/v1/files/upload/stream'=>Http::response(['success'=>true,'data'=>['file_id'=>'fake','file_url'=>'https://files.evolink.ai/fake.png','expires_at'=>now()->addHours(72)->toIso8601String()]])]);
  $svc=app(MarketingUploadCheckpoint::class);$a=$svc->uploadOne($p->id,$p->owner_id,$plan->id);$b=$svc->uploadOne($p->id,$p->owner_id,$plan->id);
  $this->assertSame('measured',$a['status']);$this->assertSame(0,$a['actual_microusd']);$this->assertSame($a['id'],$b['id']);Http::assertSentCount(3);Queue::assertNothingPushed();
 }
 public function test_unknown_fee_keeps_link_and_blocks_retry():void {
  [$p,$plan]=$this->fixture();Http::fake(['https://api.evolink.ai/v1/credits'=>Http::sequence()->push(['data'=>['user'=>['remaining_credits'=>100,'used_credits'=>20],'token'=>['used_credits'=>20]]])->push([],503),'https://files-api.evolink.ai/api/v1/files/upload/stream'=>Http::response(['success'=>true,'data'=>['file_id'=>'saved','file_url'=>'https://files.evolink.ai/saved.png','expires_at'=>now()->addHours(72)->toIso8601String()]])]);
  $svc=app(MarketingUploadCheckpoint::class);$a=$svc->uploadOne($p->id,$p->owner_id,$plan->id);$b=$svc->uploadOne($p->id,$p->owner_id,$plan->id);
  $this->assertSame('unknown',$a['status']);$this->assertSame('saved',$a['upload']['file_id']);$this->assertSame($a['id'],$b['id']);Http::assertSentCount(3);Queue::assertNothingPushed();
 }
 public function test_reuse_rejects_expired_links_and_changed_identity():void {
  [$p,$plan]=$this->fixture();$c=['data'=>['user'=>['remaining_credits'=>100,'used_credits'=>20],'token'=>['used_credits'=>20]]];
  Http::fake(['https://api.evolink.ai/v1/credits'=>Http::response($c),'https://files-api.evolink.ai/api/v1/files/upload/stream'=>Http::response(['success'=>true,'data'=>['file_id'=>'fake','file_url'=>'https://files.evolink.ai/fake.png','expires_at'=>now()->addHours(72)->toIso8601String()]])]);
  $svc=app(MarketingUploadCheckpoint::class);$a=$svc->uploadOne($p->id,$p->owner_id,$plan->id);$r=\App\Models\MarketingUploadCheckpoint::findOrFail($a['id']);
  $this->assertSame('fake',$svc->reuse($p->id,$r->scene_id,$r->role,$r->sha256)['file_id']);$this->assertNull($svc->reuse($p->id,$r->scene_id,$r->role,str_repeat('0',64)));
  $this->travel(73)->hours();$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);$svc->reuse($p->id,$r->scene_id,$r->role,$r->sha256);
 }
 public function test_out_of_grant_scope_sends_no_requests():void {
  [$p,$plan]=$this->fixture();config(['marketing.production.upload_checkpoint.project'=>(string)Str::uuid()]);Http::fake();try{app(MarketingUploadCheckpoint::class)->uploadOne($p->id,$p->owner_id,$plan->id);$this->fail('foreign project accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(423,$e->getStatusCode());}Http::assertNothingSent();
 }
 public function test_trial_activation_does_not_bypass_upload_or_voice_boundaries():void {
  [$p,$plan]=$this->fixture();$svc=app(MarketingUploadCheckpoint::class);
  $h=$plan->manifest['execution_handoff'];$this->assertFalse($svc->activateVideo($h));Http::fake();Http::assertNothingSent();
 }
 public function test_measured_frames_enable_only_bounded_native_trial_without_claiming_quality():void {
  [$p,$plan]=$this->fixture();$h=$plan->manifest['execution_handoff'];$h['profile']['voice']=['mode'=>'auto'];$h['scene_map'][0]['frames']['end_frame']=$h['scene_map'][0]['frames']['start_frame'];
  $g=config('marketing.production.upload_checkpoint');$connection=app(\App\Services\Marketing\MarketingEvolinkAdapter::class)->connectionFingerprint();
  foreach(['start_frame','end_frame'] as $role){$ref=$h['scene_map'][0]['frames'][$role]['asset'];\App\Models\MarketingUploadCheckpoint::create(['project_id'=>$p->id,'plan_id'=>$plan->id,'scene_id'=>$g['scene'],'role'=>$role,'claim'=>hash('sha256',$role),'grant_reference'=>$g['reference'],'connection'=>$connection,'sha256'=>$ref['sha256'],'asset_id'=>$ref['asset_id'],'status'=>'measured','actual_microusd'=>0,'evidence'=>['upload'=>['file_id'=>$role,'file_url'=>'https://files.evolink.ai/'.$role.'.png','expires_at'=>now()->addHours(72)->toIso8601String()]]]);}
  Http::fake();$svc=app(MarketingUploadCheckpoint::class);$this->assertTrue($svc->activateVideo($h));
  $video=config('marketing.storyboard.roles.video');$this->assertLessThanOrEqual(1500000,$video['cap_microusd']);$this->assertSame(1,config('marketing.storyboard.live_grant.max_calls'));
  $this->assertFalse(config('marketing.storyboard.audio.native.exact_script'));$this->assertFalse(config('marketing.storyboard.audio.native.lip_sync'));
  $route=app(\App\Services\Marketing\MarketingStoryboardAudioRoute::class)->select($h,$video);$this->assertSame('native',$route['route']);
  $manifest=app(\App\Services\Marketing\MarketingStoryboardProduction::class)->manifest($h,app(\App\Services\Marketing\MarketingClipSource::class)->storyboard($h),$p->id);$this->assertNotEmpty($manifest['blockers']);$this->assertSame('seedance-1.5-pro',$manifest['quote_candidate']['model']);
  $h['profile']['voice']=['mode'=>'preset'];$this->assertFalse($svc->activateVideo($h));Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_observed_charge_exceeding_remaining_budget_stops_without_retry():void {
  [$p,$plan]=$this->fixture();Http::fake(['https://api.evolink.ai/v1/credits'=>Http::sequence()->push(['data'=>['user'=>['remaining_credits'=>100,'used_credits'=>20],'token'=>['used_credits'=>20]]])->push(['data'=>['user'=>['remaining_credits'=>80,'used_credits'=>40],'token'=>['used_credits'=>40]]]),'https://files-api.evolink.ai/api/v1/files/upload/stream'=>Http::response(['success'=>true,'data'=>['file_id'=>'charged','file_url'=>'https://files.evolink.ai/charged.png','expires_at'=>now()->addHours(72)->toIso8601String()]])]);
  $svc=app(MarketingUploadCheckpoint::class);$a=$svc->uploadOne($p->id,$p->owner_id,$plan->id);$this->assertSame('over_budget',$a['status']);$this->assertEquals(20,$a['observed_credits']);$this->assertSame($a['id'],$svc->uploadOne($p->id,$p->owner_id,$plan->id)['id']);Http::assertSentCount(3);Queue::assertNothingPushed();
 }
}
