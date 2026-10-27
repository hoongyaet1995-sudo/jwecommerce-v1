<?php
namespace Tests\Feature;
use App\Services\Marketing\{MarketingStoryboardBatchApproval,MarketingMeasuredUpload};
use Illuminate\Support\Facades\Http;
class MarketingStoryboardBatchTest extends MarketingStoryboardTrialScopeTest {
 public function test_execution_prompt_uses_actual_seconds_and_only_required_frame_roles():void {
  $scene=['voiceover'=>'你好','material_plan'=>['mode'=>'single_image','roles'=>['start_frame'],'reason'=>'fixture']];$payload=['model'=>'seedance-1.5-pro','duration'=>11,'generate_audio'=>true];
  $out=app(\App\Services\Marketing\MarketingVideoPromptAdaptation::class)->compose($scene,$payload,'A presenter walks toward the container and opens its door.');
  $this->assertStringContainsString('11-second',$out['prompt']);$this->assertStringNotContainsString('last images',$out['prompt']);$this->assertStringContainsString('你好',$out['prompt']);Http::assertNothingSent();
 }
 public function test_no_batch_approval_never_enables_or_reads_provider():void {
  config(['marketing.storyboard.batch_approval'=>[]]);$this->assertFalse(app(MarketingStoryboardBatchApproval::class)->activate(['source_context'=>['project_id'=>'unapproved']]));Http::assertNothingSent();
 }
 public function test_upload_settlement_requires_three_matching_nonnegative_credit_deltas():void {
  $before=['user'=>['remaining_credits'=>100,'used_credits'=>10],'token'=>['used_credits'=>5]];$after=['user'=>['remaining_credits'=>99,'used_credits'=>11],'token'=>['used_credits'=>6]];
  $svc=app(MarketingMeasuredUpload::class);$this->assertSame(15385,$svc->cost($before,$after,10/650));$after['token']['used_credits']=5;$this->assertNull($svc->cost($before,$after,10/650));Http::assertNothingSent();
 }
}
