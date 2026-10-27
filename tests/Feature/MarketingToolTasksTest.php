<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Storage};
use App\Services\Marketing\{MarketingToolCapabilities,MarketingToolTasks,MarketingVideoCompositor,MarketingWorkspaceFilm};
class MarketingToolTasksTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 public function test_planned_tools_are_not_executable_and_forms_do_not_lock_software():void{
  $r=app(MarketingToolCapabilities::class);$all=$r->catalog();foreach(['blender.product_turntable','comfyui.masked_image'] as $id){$this->assertSame('planned',$all[$id]['status']);$this->assertFalse($all[$id]['executable']);}
  foreach(['film.compose','film.package','remotion.captions','ffmpeg.retime'] as $id){$this->assertSame('implemented',$all[$id]['status']);$this->assertCount(6,$all[$id]['forms']);$this->assertNull($all[$id]['cost']['compute_microusd']);$this->assertSame(0,$all[$id]['cost']['external_api_microusd']);}
 }
 public function test_planned_or_arbitrary_operation_cannot_be_resolved():void{
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);app(MarketingToolCapabilities::class)->requireExecutable('blender.product_turntable');
 }
 public function test_owner_scoped_legacy_description_is_readonly_and_does_not_certify_quality():void{
  [$p,$f]=$this->editingFilm();$before=$f->fresh()->toArray();$s=app(MarketingToolTasks::class)->describe($p->id,$p->owner_id,$f->id);$this->assertSame('completed',$s['status']);$this->assertSame('legacy_record',$s['evidence']);$this->assertFalse($s['quality_verified']);$this->assertSame($before,$f->fresh()->toArray());
  $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);app(MarketingToolTasks::class)->describe($p->id,$p->owner_id+100,$f->id);
 }
 public function test_enqueue_replay_uses_existing_film_and_one_job_without_network():void{
  [$p,$f]=$this->editingFilm();$f->update(['status'=>'queued']);Queue::fake();Http::preventStrayRequests();$tasks=app(MarketingToolTasks::class);$tasks->enqueue($f);$tasks->enqueue($f->fresh());Queue::assertPushed(\App\Jobs\ProcessMarketingWorkspaceVideo::class,1);$this->assertSame('queued',$tasks->describe($p->id,$p->owner_id,$f->id)['status']);Http::assertNothingSent();
 }
 public function test_worker_receipt_reuses_verified_result_without_re_render_or_source_mutation():void{
  [$p,$f]=$this->editingFilm();$input=$f->input;$f->update(['status'=>'queued']);$this->mock(MarketingVideoCompositor::class)->shouldReceive('film')->once()->andReturnUsing(function($f){$path='marketing-films/'.$f->id.'/tool.mp4';Storage::disk('local')->put($path,'local fake output');return ['path'=>$path,'sha256'=>hash('sha256','local fake output'),'frames'=>360];});
  Http::preventStrayRequests();$films=app(MarketingWorkspaceFilm::class);$films->process($f->id);$f->refresh();$this->assertSame('completed',$f->status);$this->assertSame('completed',$f->result['tool_task']['state']);$this->assertArrayHasKey('ffmpeg_binary_sha256',$f->result['tool_task']['tool_identity']);$this->assertSame($input,$f->input);
  // Simulate interruption after tool receipt committed but before parent film completed.
  $f->update(['status'=>'queued']);$films->process($f->id);$this->assertSame('completed',$f->fresh()->status);$this->assertSame(1,$f->fresh()->result['tool_task']['attempts']);Http::assertNothingSent();
 }
 public function test_failure_keeps_original_file_records_reference_and_does_not_retry():void{
  [$p,$f]=$this->editingFilm();$oldPath=$f->output_path;$oldHash=$f->output_sha256;$f->update(['status'=>'queued']);$this->mock(MarketingVideoCompositor::class)->shouldReceive('film')->once()->andThrow(new \RuntimeException('private paths and command arguments must not escape'));Queue::fake();Http::preventStrayRequests();$films=app(MarketingWorkspaceFilm::class);$films->process($f->id);$films->process($f->id);$f->refresh();$this->assertSame('failed',$f->status);$this->assertSame('failed',$f->result['tool_task']['state']);$this->assertNotEmpty($f->result['tool_task']['error_reference']);$this->assertStringNotContainsString('private paths',json_encode($f->result['tool_task']));$this->assertSame($oldPath,$f->output_path);$this->assertSame($oldHash,$f->output_sha256);Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_missing_runtime_is_not_offered_and_planned_tools_never_become_candidates():void{
  config(['marketing.production.browser_binary'=>base_path('no-such-browser')]);$r=app(MarketingToolCapabilities::class);$this->assertFalse($r->runtime('film.compose')['available']);$this->assertArrayNotHasKey('film.compose',$r->available('scenario_story'));$this->assertArrayNotHasKey('blender.product_turntable',$r->available('product_showcase'));$this->assertStringNotContainsString('available configured capabilities blender',$r->instruction());
 }
 public function test_unknown_recipe_cannot_dispatch_or_accept_arbitrary_commands():void{
  [$p,$f]=$this->editingFilm();$i=$f->input;$i['version']='blender';$i['command']='arbitrary script';$f->update(['status'=>'queued','input'=>$i]);Queue::fake();try{app(MarketingToolTasks::class)->enqueue($f);$this->fail('unknown recipe must reject');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(422,$e->getStatusCode());}Queue::assertNothingPushed();
 }
 public function test_tampered_cached_output_is_not_reused_or_automatically_rendered_again():void{
  [$p,$f]=$this->editingFilm();$f->update(['status'=>'queued']);$this->mock(MarketingVideoCompositor::class)->shouldReceive('film')->once()->andReturnUsing(function($f){$path='marketing-films/'.$f->id.'/cache.mp4';Storage::disk('local')->put($path,'original rendered fake');return ['path'=>$path,'sha256'=>hash('sha256','original rendered fake')];});
  $films=app(MarketingWorkspaceFilm::class);$films->process($f->id);$f->refresh();Storage::disk('local')->put($f->output_path,'tampered');$f->update(['status'=>'queued']);$films->process($f->id);$f->refresh();$this->assertSame('failed',$f->status);$this->assertSame('output_verification_failed',$f->result['tool_task']['error_code']);$this->assertSame(1,$f->result['tool_task']['attempts']);
 }
 public function test_worker_interruption_updates_task_without_queueing_a_retry():void{
  [$p,$f]=$this->editingFilm();$f->update(['status'=>'queued']);Queue::fake();app(MarketingToolTasks::class)->enqueue($f);Queue::fake();$f->update(['status'=>'rendering']);(new \App\Jobs\ProcessMarketingWorkspaceVideo('film',$f->id))->failed(new \RuntimeException('private command'));$f->refresh();$this->assertSame('unknown',$f->status);$this->assertSame('unknown',$f->result['tool_task']['state']);$this->assertSame('worker_interrupted',$f->result['tool_task']['error_code']);$this->assertFalse($f->result['tool_task']['automatic_retry']);Queue::assertNothingPushed();
 }
 public function test_recipe_change_during_render_cannot_publish_output_for_changed_inputs():void{
  [$p,$f]=$this->editingFilm();$oldHash=$f->output_sha256;$f->update(['status'=>'queued']);$this->mock(MarketingVideoCompositor::class)->shouldReceive('film')->once()->andReturnUsing(function($film){$input=$film->input;$input['cta']='changed while rendering';\App\Models\MarketingVideoFilm::whereKey($film->id)->update(['input'=>json_encode($input)]);$path='marketing-films/'.$film->id.'/changed.mp4';Storage::disk('local')->put($path,'changed render');return ['path'=>$path,'sha256'=>hash('sha256','changed render')];});
  app(MarketingWorkspaceFilm::class)->process($f->id);$f->refresh();$this->assertSame('failed',$f->status);$this->assertSame($oldHash,$f->output_sha256);$this->assertSame('failed',$f->result['tool_task']['state']);$this->assertNotEmpty($f->result['tool_task']['error_reference']);
 }

 public function test_adapter_envelope_cannot_borrow_foreign_subject_or_accept_raw_script_task():void{
  [$p,$f]=$this->editingFilm();$this->mock(MarketingVideoCompositor::class)->shouldNotReceive('film');$adapter=app(\App\Services\Marketing\MarketingLocalFilmToolAdapter::class);
  try{$adapter->execute(['version'=>MarketingToolTasks::VERSION,'subject'=>['type'=>'script','command'=>'arbitrary']]);$this->fail('script not allowed');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(422,$e->getStatusCode());}
  $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);$adapter->execute(['version'=>MarketingToolTasks::VERSION,'subject'=>['type'=>'film','id'=>$f->id,'project_id'=>$p->id,'owner_id'=>$p->owner_id+100],'source_digest'=>str_repeat('a',64)]);
 }

}
