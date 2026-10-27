<?php
namespace Tests\Feature;
use App\Services\Marketing\MarketingEconomyTrialGrant;
use Illuminate\Support\Facades\Http;
class MarketingEconomyTrialGrantTest extends MarketingStoryboardTrialScopeTest {
 public function test_snapshot_permission_queries_do_not_reenter_activation():void {
  [$p,$s]=$this->sample();config(['marketing.storyboard.manual_testing_file'=>'missing-file','marketing.storyboard.economy_trial'=>['project'=>$p->id,'scene'=>$s['scenes'][1]['scene_id'],'expires_at'=>now()->addHour()->toIso8601String()]]);
  $workspace=$this->mock(\App\Services\Marketing\MarketingStoryboardWorkspace::class);$workspace->shouldReceive('project')->andReturn($p);
  $workspace->shouldReceive('snapshot')->twice()->andReturnUsing(function()use($p,$s){app(\App\Services\Marketing\MarketingStoryboardProvider::class)->allowed('semantic',$p->id);return $s;});
  $this->assertFalse(app(MarketingEconomyTrialGrant::class)->activateProject($p->id));Http::assertNothingSent();
 }
 public function test_absent_grant_does_not_enable_paid_calls_or_read_network():void {
  config(['marketing.storyboard.economy_trial'=>[]]);
  $this->assertFalse(app(MarketingEconomyTrialGrant::class)->activate(['source_context'=>['project_id'=>'not-approved']]));
  Http::assertNothingSent();
 }
 public function test_wrong_story_or_scope_never_enables_economy_trial():void {
  config(['marketing.storyboard.economy_trial'=>['project'=>'approved','scene'=>'second','story_version'=>17,'expires_at'=>now()->addHour()->toIso8601String(),'cap_microusd'=>450000,'max_posts'=>4]]);
  $this->assertFalse(app(MarketingEconomyTrialGrant::class)->activate(['source_context'=>['project_id'=>'approved'],'story_version'=>18,'scene_map'=>[['scene_id'=>'second','seconds'=>6]],'profile'=>['voice'=>['mode'=>'auto']]]));
  Http::assertNothingSent();
 }
 public function test_scoped_trial_uses_measured_images_native_route_and_two_requests_in_045_budget():void {
  [$p,$s]=$this->sample();config(['marketing.native.api_key'=>'fixture']);$h=app(\App\Services\Marketing\MarketingStoryboardHandoff::class)->build($p->id,$p->owner_id,$s['story_id']);$h=app(\App\Services\Marketing\MarketingProductionScope::class)->select($h,$s['scenes'][1]['scene_id']);$h['profile']['voice']=['mode'=>'auto'];$h['scene_map'][0]['frames']['end_frame']=$h['scene_map'][0]['frames']['start_frame'];
  config(['marketing.native.api_key'=>'fixture','marketing.storyboard.economy_trial'=>['project'=>$p->id,'scene'=>$h['scene_map'][0]['scene_id'],'story_version'=>$h['story_version'],'scene_digest'=>hash('sha256',json_encode($h['scene_map'][0],JSON_THROW_ON_ERROR)),'connection'=>app(\App\Services\Marketing\MarketingEvolinkAdapter::class)->connectionFingerprint(),'model'=>'seedance-1.5-pro','reference'=>'fake-economy','verified_at'=>'fixture','expires_at'=>now()->addHour()->toIso8601String(),'cap_microusd'=>450000,'max_posts'=>4]]);
  $this->mock(\App\Services\Marketing\MarketingUploadCheckpoint::class)->shouldReceive('reuse')->andReturn(['file_url'=>'https://files.evolink.ai/fake.png','expires_at'=>now()->addDay()->toIso8601String()]);
  Http::fake(['https://api.evolink.ai/v1/models'=>Http::response(['data'=>[['id'=>'seedance-1.5-pro']]]),'https://api.evolink.ai/v1/credits'=>Http::response(['data'=>['user'=>['remaining_credits'=>652.255]]])]);
  $this->assertTrue(app(MarketingEconomyTrialGrant::class)->activate($h));$this->assertSame('seedance-1.5-pro',config('marketing.storyboard.roles.video.model'));$this->assertSame(450000,config('marketing.storyboard.live_grant.max_total_microusd'));$this->assertSame(2,config('marketing.storyboard.live_grant.max_calls'));$this->assertTrue(config('marketing.storyboard.roles.upload.reuse_only'));Http::assertSentCount(2);
 }
 public function test_prompt_adaptation_is_cached_and_preserves_exact_script():void {
  [$p,$s]=$this->sample();config(['marketing.storyboard.economy_trial'=>[],'marketing.storyboard.manual_testing_file'=>'missing-file','marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['reference'=>'fake-prompt','project_id'=>$p->id,'owner_id'=>$p->owner_id,'roles'=>['semantic'],'expires_at'=>now()->addHour()->toIso8601String(),'max_total_microusd'=>450000,'max_calls'=>2], 'marketing.storyboard.roles.semantic'=>['model'=>'gpt-5.6-luna','version'=>'fake-prompt','cap_microusd'=>50000,'pricing_verified_at'=>'fixture','input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2]]);
  \App\Models\AiConnection::updateOrCreate(['provider'=>'openai'],['enabled'=>true,'verified_at'=>now(),'api_key'=>'fixture','models'=>[['id'=>'gpt-5.6-luna']],'version'=>1]);
  $scene=$s['scenes'][1];$a=app(\App\Services\Marketing\MarketingPreparationBudget::class)->authorize($p->id,$p->owner_id,(string)\Illuminate\Support\Str::uuid(),$s['story_version'],[$scene['revision_id']],450000);
  $this->mock(\App\Services\AI\ProviderTransport::class)->shouldReceive('json')->once()->andReturn(['id'=>'fake-response','status'=>'completed','usage'=>['input_tokens'=>1000,'output_tokens'=>100],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['direction'=>str_repeat('Warehouse, blue container, host gesture. ',35)])]]]]]);
  $payload=['model'=>'seedance-1.5-pro','duration'=>6,'quality'=>'720p','generate_audio'=>true,'prompt'=>str_repeat('x',3000)];$svc=app(\App\Services\Marketing\MarketingVideoPromptAdaptation::class);$first=$svc->adapt($p->id,$a['id'],$scene,$payload);$second=$svc->adapt($p->id,$a['id'],$scene,$payload);
  $this->assertSame($first,$second);$this->assertStringContainsString($scene['voiceover'],$first['prompt']);$this->assertLessThanOrEqual(2000,strlen($first['prompt']));$this->assertSame(1,\App\Models\MarketingStoryboardProviderCall::where('project_id',$p->id)->count());$this->assertSame(320,\App\Models\MarketingStoryboardProviderCall::where('project_id',$p->id)->first()->actual_microusd);
 }
}
