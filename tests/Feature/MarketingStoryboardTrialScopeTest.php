<?php
namespace Tests\Feature;

use App\Models\{MarketingRevision,MarketingScene,MarketingSceneRevision};
use App\Services\Marketing\{MarketingStoryboardWorkspace,MarketingStoryboardUpload,MarketingStoryboardHandoff,MarketingProductionPreparation,MarketingStoryboardProduction};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingStoryboardTrialScopeTest extends TestCase
{
 use MarketingResearchFixtures;
 protected function setUp():void{parent::setUp();$this->artisan('migrate:fresh')->run();}
 protected function sample():array{
  $p=$this->researchProject();$r=MarketingRevision::create(['project_id'=>$p->id,'actor_id'=>$p->owner_id,'number'=>1,'document'=>['scenes'=>[['visual'=>'Warehouse opening','duration'=>5,'voiceover'=>'','subtitle'=>''],['visual'=>'Container shot','duration'=>6,'voiceover'=>'设备、材料和一般货物。','subtitle'=>'设备、材料和一般货物。']]],'context'=>[]]);$p->update(['current_revision_id'=>$r->id]);
  $s=app(MarketingStoryboardWorkspace::class)->initialize($p->id,$p->owner_id,(string)Str::uuid(),$p->version);$ref=app(MarketingStoryboardUpload::class)->store($p->id,$p->owner_id,UploadedFile::fake()->image('scene.png'),true);
  foreach($s['scenes'] as $scene){$old=MarketingSceneRevision::find($scene['revision_id']);$c=$old->content;$c['frames']['start_frame']['asset']=$ref;$c['frames']['start_frame']['readiness']='qa_passed';$next=MarketingSceneRevision::create(['scene_id'=>$old->scene_id,'parent_id'=>$old->id,'content'=>$c,'signature'=>$old->signature]);MarketingScene::find($old->scene_id)->update(['current_revision_id'=>$next->id]);}
  $s=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);app(MarketingStoryboardHandoff::class)->confirm($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version']);return [$p,$s];
 }
 public function test_trial_freezes_complete_handoff_but_quotes_only_selected_scene():void{
  [$p,$s]=$this->sample();$plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$s['scenes'][1]['scene_id']);
  $this->assertCount(2,$plan->manifest['handoff']['scene_map']);$this->assertCount(1,$plan->manifest['clips']);$this->assertSame($s['scenes'][1]['scene_id'],$plan->manifest['clips'][0]['scene_id']);$this->assertSame('scene_trial',$plan->manifest['scope']['mode']);$this->assertSame(6,$plan->manifest['execution_handoff']['timing']['content_seconds']);
  $this->assertSame($plan->id,app(MarketingProductionPreparation::class)->storyboardPreview($p->id,$p->owner_id)['plan']->id);Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_foreign_trial_id_and_request_scope_change_are_rejected():void{
  [$p,$s]=$this->sample();$id=(string)Str::uuid();$svc=app(MarketingProductionPreparation::class);$svc->prepareStoryboard($p->id,$p->owner_id,$id,$s['story_version'],$s['scenes'][1]['scene_id']);
  try{$svc->prepareStoryboard($p->id,$p->owner_id,$id,$s['story_version']);$this->fail('scope collision accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);$svc->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],(string)Str::uuid());
 }
 public function test_account_lookup_is_read_only_cached_and_public_estimate_never_authorizes():void{
  [$p,$s]=$this->sample();config(['marketing.native.api_key'=>'fixture-evolink']);
  Http::fake(['https://api.evolink.ai/v1/models'=>Http::response(['data'=>[['id'=>'seedance-2.0-image-to-video']]]),'https://api.evolink.ai/v1/credits'=>Http::response(['data'=>['user'=>['remaining_credits'=>100]]])]);
  $svc=app(\App\Services\Marketing\MarketingProductionQuoteEvidence::class);$a=$svc->refresh($p->id,$p->owner_id);$this->assertSame($a,$svc->refresh($p->id,$p->owner_id));Http::assertSentCount(2);Http::assertSent(fn($r)=>$r->method()==='GET');
  $h=app(MarketingStoryboardHandoff::class)->build($p->id,$p->owner_id,$s['story_id']);$h=app(\App\Services\Marketing\MarketingProductionScope::class)->select($h,$s['scenes'][1]['scene_id']);$q=$svc->candidate($p->id,$h,app(\App\Services\Marketing\MarketingClipSource::class)->storyboard($h));
  $this->assertSame('per_scene_candidate',$q['audio_route']);$this->assertSame('seedance-1.5-pro',$q['model']);$this->assertSame(2,$q['total_posts']);$this->assertNull($q['cap_microusd']);$this->assertFalse($q['generation_authorized']);$this->assertNotEmpty($q['blockers']);Queue::assertNothingPushed();
 }
 public function test_authentication_failure_is_cached_without_automatic_retry():void{
  [$p]=$this->sample();config(['marketing.native.api_key'=>'bad-fixture']);Http::fake(['https://api.evolink.ai/v1/models'=>Http::response([],401)]);
  $svc=app(\App\Services\Marketing\MarketingProductionQuoteEvidence::class);$a=$svc->refresh($p->id,$p->owner_id);$svc->refresh($p->id,$p->owner_id);$this->assertSame('authentication_blocked',$a['account_status']);Http::assertSentCount(1);Queue::assertNothingPushed();
 }
 public function test_adopted_framing_reason_does_not_restate_old_crop_rejection():void{
  $c=['material_plan'=>['mode'=>'single_image','roles'=>['start_frame'],'reason'=>'旧要求：必须胸口以上中近景'],'frames'=>['start_frame'=>['employee_acceptance'=>['sync_framing'=>true]]]];
  $out=app(\App\Services\Marketing\MarketingVideoRoutes::class)->intent($c);$this->assertStringContainsString('已确认',$out['reason']);$this->assertStringNotContainsString('胸口以上',$out['reason']);$this->assertSame(['start_frame'],$out['roles']);
 }
 public function test_trial_progress_is_labeled_as_trial_before_completion():void{
  [$p,$s]=$this->sample();$plan=app(MarketingProductionPreparation::class)->prepareStoryboard($p->id,$p->owner_id,(string)Str::uuid(),$s['story_version'],$s['scenes'][1]['scene_id']);
  $run=\App\Models\MarketingProduction::create(['project_id'=>$p->id,'owner_id'=>$p->owner_id,'plan_id'=>$plan->id,'status'=>'queued','result'=>['message'=>'Old paused message']]);
  $state=app(MarketingStoryboardProduction::class)->state($p->id,$p->owner_id,$run->id);$this->assertSame('scene_trial',$state['scope']);$this->assertNull($state['film_id']);$this->assertNull($state['message']);Http::assertNothingSent();Queue::assertNothingPushed();
 }
}
