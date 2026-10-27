<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage};
use App\Services\Marketing\MarketingClipAnalysis;

class MarketingClipAnalysisTest extends TestCase
{
 use RefreshDatabase,MarketingEditingFixtures;
 public function test_same_current_source_reuses_evidence_and_changed_hash_cannot_reuse():void {
  [$p,$film,$clips]=$this->editingFilm();$service=app(MarketingClipAnalysis::class);
  $a=$service->ensure($clips[0]);$b=$service->ensure($clips[0]);$this->assertSame($a->id,$b->id);$this->assertSame('queued',$a->status);
  $clips[0]->update(['output_sha256'=>hash('sha256','changed')]);Storage::disk('local')->put($clips[0]->output_path,'changed');$new=$service->ensure($clips[0]);$this->assertNotSame($a->id,$new->id);Http::assertNothingSent();
 }
 public function test_revoked_source_is_rejected_before_analysis():void {
  [$p,$film,$clips]=$this->editingFilm();$p->assets()->whereKey($clips[0]->input['image']['asset_id'])->update(['authorized'=>false]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingClipAnalysis::class)->ensure($clips[0]);
 }
 private function localEvidence(bool $safe=true):array{return ['fps'=>24,'frame_count'=>360,'audio_present'=>true,'speech_intervals'=>[['in_frame'=>0,'out_frame'=>70,'reason'=>'speech']],'speech_end_frame'=>61,'words'=>[['start'=>0,'end'=>2.54,'text'=>'货箱']],'silence_intervals'=>[],'transcript'=>'货箱','script_similarity'=>.8,'safe_speech_timing'=>$safe,'candidate_in_frame'=>0,'candidate_out_frame'=>75,'frame_samples'=>[],'protected_intervals'=>[['in_frame'=>0,'out_frame'=>70,'reason'=>'speech']],'minimum_frames'=>24,'method'=>'fixture-timing','limitations'=>[]];}
 public function test_local_timing_and_visual_review_are_cached_without_overwriting_script():void {
  [$p,$film,$clips]=$this->editingFilm();$original=$clips[0]->input['spoken_text'];$service=app(MarketingClipAnalysis::class);$a=$service->ensure($clips[0]);
  $this->mock(\App\Services\Marketing\MarketingEditingLocalMedia::class)->shouldReceive('analyze')->once()->andReturn($this->localEvidence());
  $this->partialMock(\App\Services\Marketing\MarketingEditingVisionClient::class)->shouldReceive('inspect')->once()->andReturn(['can_trim_head'=>false,'protected_from_frame'=>0,'can_trim_tail'=>true,'protected_until_frame'=>74,'confidence'=>'high','observations'=>'fixture samples','usage'=>[],'actual_usd'=>.001]);
  $service->process($a->id);$service->process($a->id);$a->refresh();$this->assertSame('completed',$a->status);$this->assertTrue($service->evidence($a)['can_trim_tail']);$this->assertSame($original,$clips[0]->fresh()->input['spoken_text']);
  $e=$a->evidence;$a->update(['evidence'=>array_reverse($e,true)]);$this->assertSame($e['digest'],$service->evidence($a->fresh())['digest']);$this->assertDatabaseCount('marketing_ai_runs',2); // one material fixture run + one visual run
  Http::assertNothingSent();
 }
 public function test_uncertain_speech_or_silent_product_action_retains_protected_visual_range():void {
  [$p,$film,$clips]=$this->editingFilm();$service=app(MarketingClipAnalysis::class);$a=$service->ensure($clips[0]);
  $this->mock(\App\Services\Marketing\MarketingEditingLocalMedia::class)->shouldReceive('analyze')->once()->andReturn($this->localEvidence(false));
  $this->partialMock(\App\Services\Marketing\MarketingEditingVisionClient::class)->shouldReceive('inspect')->once()->andReturn(['can_trim_head'=>false,'can_trim_tail'=>true,'protected_until_frame'=>75,'confidence'=>'high','usage'=>[],'actual_usd'=>0]);
  $service->process($a->id);$e=$service->evidence($a->fresh());$this->assertFalse($e['can_trim_tail']);$this->assertSame(360,$e['protected_intervals'][1]['out_frame']);
 }
 public function test_missing_local_runtime_blocks_before_paid_request():void {
  [$p,$film,$clips]=$this->editingFilm();$service=app(MarketingClipAnalysis::class);$a=$service->ensure($clips[0]);
  $this->mock(\App\Services\Marketing\MarketingEditingLocalMedia::class)->shouldReceive('analyze')->once()->andThrow(new \App\Services\Marketing\MarketingProductionFailure('editing_asr_not_configured','blocked'));
  $this->partialMock(\App\Services\Marketing\MarketingEditingVisionClient::class)->shouldNotReceive('inspect');$service->process($a->id);$this->assertSame('blocked',$a->fresh()->status);$this->assertNull($a->fresh()->run_id);Http::assertNothingSent();
 }
 public function test_unknown_visual_response_has_one_claim_and_no_automatic_retry():void {
  [$p,$film,$clips]=$this->editingFilm();$service=app(MarketingClipAnalysis::class);$a=$service->ensure($clips[0]);
  $this->mock(\App\Services\Marketing\MarketingEditingLocalMedia::class)->shouldReceive('analyze')->once()->andReturn($this->localEvidence());
  $this->partialMock(\App\Services\Marketing\MarketingEditingVisionClient::class)->shouldReceive('inspect')->once()->andThrow(new \App\Services\AI\ProviderFailure('timeout'));
  $service->process($a->id);$service->process($a->id);$this->assertSame('unknown',$a->fresh()->status);$this->assertSame(1,\App\Models\MarketingAiRun::where('action','editing_vision')->count());Http::assertNothingSent();
 }
 public function test_omitted_final_clause_is_protected_by_independent_audio_activity():void {
  $e=['audio_present'=>true,'frame_count'=>360,'silence_intervals'=>[['kind'=>'start','frame'=>0],['kind'=>'end','frame'=>10],['kind'=>'start','frame'=>300],['kind'=>'end','frame'=>360]]];
  $guards=\App\Services\Marketing\MarketingEditingLocalMedia::audioProtection($e);$this->assertSame(309,max(array_column($guards,'out_frame')));$this->assertSame(5,min(array_column($guards,'in_frame')));
 }
}
