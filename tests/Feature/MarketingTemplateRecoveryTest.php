<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use App\Models\{User,MarketingProject,MarketingTemplateGrant,MarketingTemplateRun,MarketingTemplateCall};
class MarketingTemplateRecoveryTest extends TestCase{
 use RefreshDatabase;
 private function runFixture():MarketingTemplateRun{
  Queue::fake();$u=User::factory()->create();$p=MarketingProject::create(['owner_id'=>$u->id,'source_type'=>'product','name'=>'Recovery','snapshot'=>[],'brief'=>[],'version'=>1]);$g=MarketingTemplateGrant::create(['owner_id'=>$u->id,'product_id'=>1,'cap_microusd'=>100000,'max_llm'=>2,'max_tts'=>1,'active'=>true,'expires_at'=>now()->addDay(),'pricing'=>[],'policy'=>[]]);
  $r=MarketingTemplateRun::create(['project_id'=>$p->id,'owner_id'=>$u->id,'grant_id'=>$g->id,'request_id'=>(string)Str::uuid(),'input_digest'=>str_repeat('a',64),'input'=>['project_version'=>1],'status'=>'paused','error'=>['code'=>'template_local_render_failed'],'result'=>['voice'=>['path'=>'saved.mp3','sha256'=>str_repeat('a',64)]]]);
  MarketingTemplateCall::create(['id'=>hash('sha256',$r->id),'run_id'=>$r->id,'grant_id'=>$g->id,'stage'=>'voice-0','kind'=>'tts','payload_hash'=>str_repeat('b',64),'client_request_id'=>(string)Str::uuid(),'status'=>'completed','reserved_microusd'=>1000,'actual_microusd'=>100]);return $r;
 }
 public function test_only_known_paid_results_can_be_enqueued_once_for_free_recovery():void{
  $r=$this->runFixture();$s=app(\App\Services\Marketing\MarketingTemplateRecovery::class);$this->assertTrue($s->enqueue($r));$this->assertFalse($s->enqueue($r->fresh()));$this->assertSame('queued_recovery',$r->fresh()->status);Queue::assertPushed(\App\Jobs\MarketingTemplateJob::class,1);$this->assertDatabaseCount('marketing_template_calls',1);
 }
 public function test_inherited_voice_can_recover_only_when_source_and_descriptor_match():void{
  $source=$this->runFixture();$source->update(['status'=>'completed']);$input=['project_version'=>1,'voice_source_run_id'=>$source->id];$child=MarketingTemplateRun::create(['project_id'=>$source->project_id,'owner_id'=>$source->owner_id,'grant_id'=>$source->grant_id,'request_id'=>(string)Str::uuid(),'input_digest'=>\App\Services\Marketing\MarketingTemplateProjects::hash($input),'input'=>$input,'status'=>'paused','error'=>['code'=>'template_local_render_failed'],'result'=>$source->result]);$proof=app(\App\Services\Marketing\MarketingTemplateVoice::class);$this->assertTrue($proof->known($child));$child->update(['result'=>['voice'=>['path'=>'different.mp3','sha256'=>str_repeat('a',64)]]]);$this->assertFalse($proof->known($child->fresh()));Queue::assertNothingPushed();$this->assertDatabaseCount('marketing_template_calls',1);
 }
 public function test_unknown_outcome_and_semantic_failure_never_trigger_recovery():void{
  $r=$this->runFixture();$s=app(\App\Services\Marketing\MarketingTemplateRecovery::class);$r->update(['error'=>['code'=>'semantic_check_rejected']]);$this->assertFalse($s->enqueue($r));$r->update(['error'=>['code'=>'template_local_render_failed']]);MarketingTemplateCall::firstOrFail()->update(['status'=>'unknown']);$this->assertFalse($s->enqueue($r));Queue::assertNothingPushed();
 }
}
