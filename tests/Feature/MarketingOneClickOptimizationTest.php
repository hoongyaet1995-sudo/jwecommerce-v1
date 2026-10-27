<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Http};
use Illuminate\Support\Str;
use App\Models\{User,MarketingAiRun,MarketingVideoFilm};
use App\Services\Marketing\{MarketingOptimization,MarketingOptimizationClient,MarketingEditingDigest};
use App\Jobs\ProcessMarketingOptimization;

class MarketingOneClickOptimizationTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 private function source():array{config(['marketing.optimization.semantic_enabled'=>false]);Queue::fake();[$p,$f]=$this->editingFilm();$this->actingAs(User::findOrFail($p->owner_id));return [$p,$f];}
 private function report($p,$f,array $recs=[]):MarketingAiRun{return MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>MarketingOptimization::ACTION,'status'=>'completed','usage'=>['owner_id'=>$p->owner_id,'film_id'=>$f->id,'base_hash'=>$f->output_sha256,'recipe_digest'=>MarketingEditingDigest::hash($f->input),'model'=>MarketingOptimizationClient::MODEL,'stage'=>'completed','answer'=>['recommendations'=>$recs]]]);}
 private function url($p,$f):string{return '/admin/marketing/workspace/'.$p->id.'/optimization/'.$f->id.'/one-click';}
 private function body($f,$r=null):array{return ['request_id'=>(string)Str::uuid(),'base_hash'=>$f->output_sha256]+($r?['run_id'=>$r->id]:[]);}
 public function test_cached_report_is_reused_and_duplicate_clicks_queue_only_one_application():void {
  [$p,$f]=$this->source();$r=$this->report($p,$f);
  $this->postJson($this->url($p,$f),$this->body($f,$r))->assertOk()->assertJsonPath('run.id',$r->id)->assertJsonPath('run.one_click.status','queued');
  $this->postJson($this->url($p,$f),$this->body($f,$r))->assertOk()->assertJsonPath('run.id',$r->id);
  Queue::assertPushed(ProcessMarketingOptimization::class,1);$this->assertSame(1,MarketingAiRun::where('action',MarketingOptimization::ACTION)->count());Http::assertNothingSent();
 }
 public function test_one_click_requires_consent_from_post_and_read_does_not_apply():void {
  [$p,$f]=$this->source();$r=$this->report($p,$f);
  $this->getJson(route('marketing.workspace.optimization.show',[$p->id,$f->id]).'?run='.$r->id)->assertOk()->assertJsonPath('run.one_click',null);
  $this->assertArrayNotHasKey('one_click',$r->fresh()->usage);Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_other_cached_report_for_the_same_source_cannot_duplicate_one_click():void {
  [$p,$f]=$this->source();$first=$this->report($p,$f);$second=$this->report($p,$f);
  $this->postJson($this->url($p,$f),$this->body($f,$first))->assertOk();
  $this->postJson($this->url($p,$f),$this->body($f,$second))->assertOk()->assertJsonPath('run.id',$first->id);
  Queue::assertPushed(ProcessMarketingOptimization::class,1);Http::assertNothingSent();
 }
 public function test_interrupted_cached_application_reports_failure_without_rescheduling():void {
  [$p,$f]=$this->source();$r=$this->report($p,$f);$this->postJson($this->url($p,$f),$this->body($f,$r))->assertOk();$this->travel(21)->minutes();
  $this->getJson(route('marketing.workspace.optimization.show',[$p->id,$f->id]).'?run='.$r->id)->assertOk()->assertJsonPath('run.one_click.status','failed');Queue::assertPushed(ProcessMarketingOptimization::class,1);Http::assertNothingSent();
 }
 public function test_foreign_owner_and_wrong_source_cannot_start_one_click():void {
  [$p,$f]=$this->source();$r=$this->report($p,$f);$body=$this->body($f,$r);$body['base_hash']=str_repeat('0',64);
  $this->postJson($this->url($p,$f),$body)->assertConflict();
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->postJson($this->url($p,$f),$this->body($f,$r))->assertNotFound();Queue::assertNothingPushed();
 }
 public function test_background_job_applies_only_safe_candidates_once_and_marks_the_version():void {
  [$p,$f]=$this->source();$rec=fn($id,$more=[])=>array_replace(['id'=>$id,'selectable'=>true,'confidence'=>'high','needs_speech_optin'=>false,'action'=>'adjust','capability'=>'music','target_id'=>'music','scene_number'=>0,'patch'=>['volume'=>12]],$more);
  $r=$this->report($p,$f,[$rec('safe'),$rec('uncertain',['confidence'=>'uncertain']),$rec('speech',['needs_speech_optin'=>true]),$rec('keep',['action'=>'keep']),$rec('unavailable',['selectable'=>false])]);
  $this->postJson($this->url($p,$f),$this->body($f,$r))->assertOk();
  $new=$f->replicate(['id','fingerprint']);$new->fingerprint=hash('sha256','one-click-fixture');$new->status='queued';$new->save();
  $service=$this->partialMock(MarketingOptimization::class);$service->shouldReceive('adopt')->once()->withArgs(fn($run,$request,$selected,$speech)=>$run->id===$r->id&&$selected===['safe']&&!$speech)->andReturn($new);
  (new ProcessMarketingOptimization($r->id))->handle();(new ProcessMarketingOptimization($r->id))->handle();
  $this->assertSame($r->id,$new->fresh()->result['ai_optimization']['run_id']);
  $this->getJson(route('marketing.workspace.optimization.show',[$p->id,$f->id]).'?run='.$r->id)->assertOk()->assertJsonPath('run.one_click.film_id',$new->id)->assertJsonPath('run.one_click.status','rendering');Http::assertNothingSent();
 }
 public function test_no_safe_changes_finishes_without_rendering_or_paid_retry():void {
  [$p,$f]=$this->source();$r=$this->report($p,$f,[['id'=>'keep','selectable'=>false,'confidence'=>'high','action'=>'keep']]);$this->postJson($this->url($p,$f),$this->body($f,$r))->assertOk();$count=MarketingVideoFilm::count();
  (new ProcessMarketingOptimization($r->id))->handle();
  $this->assertSame('unchanged',$r->fresh()->usage['one_click']['status']);$this->assertSame($count,MarketingVideoFilm::count());Http::assertNothingSent();
 }
 public function test_one_click_version_has_its_own_label_in_existing_history():void {
  [$p,$f]=$this->source();$f->update(['result'=>['ai_optimization'=>['run_id'=>(string)Str::uuid()]]]);
  $this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'finished','film'=>$f->id]))->assertOk()->assertSee('AI 优化版 1')->assertSee('data-one-click');Http::assertNothingSent();
 }
 public function test_new_analysis_queues_once_and_failed_checks_are_never_automatically_retried():void {
  [$p,$f]=$this->source();$r=$this->postJson($this->url($p,$f),$this->body($f))->assertOk()->json('run.id');
  $run=MarketingAiRun::findOrFail($r);$run->update(['status'=>'unknown']);(new ProcessMarketingOptimization($r))->failed(null);
  $this->postJson($this->url($p,$f),$this->body($f))->assertOk()->assertJsonPath('run.one_click.status','failed');
  Queue::assertPushed(ProcessMarketingOptimization::class,1);$this->assertSame(1,MarketingAiRun::where('action',MarketingOptimization::ACTION)->count());Http::assertNothingSent();
 }
 public function test_fresh_analysis_continues_to_local_application_without_a_second_click():void {
  [$p,$f]=$this->source();$id=$this->postJson($this->url($p,$f),$this->body($f))->assertOk()->json('run.id');
  $rec=['id'=>'safe','selectable'=>true,'confidence'=>'high','needs_speech_optin'=>false,'action'=>'adjust','capability'=>'music','target_id'=>'music','scene_number'=>0,'patch'=>['volume'=>12]];
  $this->partialMock(\App\Services\Marketing\MarketingOptimizationEvidence::class)->shouldReceive('collect')->once()->andReturn(['samples'=>[]]);
  $this->mock(MarketingOptimizationClient::class)->shouldReceive('inspect')->once()->andReturn(['answer'=>[],'usage'=>[],'model'=>MarketingOptimizationClient::MODEL,'response_id'=>'fixture-response']);
  $new=$f->replicate(['id','fingerprint']);$new->fingerprint=hash('sha256','fresh-one-click-fixture');$new->status='queued';$new->save();
  $service=$this->partialMock(MarketingOptimization::class);$service->shouldReceive('validateAnswer')->once()->andReturn(['recommendations'=>[$rec]]);$service->shouldReceive('adopt')->once()->andReturn($new);
  (new ProcessMarketingOptimization($id))->handle();(new ProcessMarketingOptimization($id))->handle();
  $run=MarketingAiRun::findOrFail($id);$this->assertSame('completed',$run->status);$this->assertSame($new->id,$run->usage['one_click']['film_id']);Http::assertNothingSent();
 }
}
