<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use App\Models\{User,MarketingPostAutomation,MarketingAutomationRun};
use App\Services\Marketing\{MarketingAutomationRunner,MarketingPostAutomations,MarketingFreePosts,MarketingPosts};

class MarketingAutomationRunnerTest extends TestCase
{
 use RefreshDatabase;
 private function occurrence():object {
  config(['marketing.automation_cron_enabled'=>true]);Http::preventStrayRequests();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
  $a=MarketingPostAutomation::create(['id'=>(string)Str::uuid(),'user_id'=>$u->id,'merchant_key'=>app(MarketingPosts::class)->merchant(),'name'=>'Test','preset'=>'spotlight','version'=>1,'settings'=>['days'=>[1],'time'=>'10:00'],'schedule_enabled'=>true,'schedule_epoch'=>'2026-11-01 00:00:00']);
  $id=(string)Str::uuid();DB::table('marketing_automation_occurrences')->insert(['id'=>$id,'automation_id'=>$a->id,'scheduled_at'=>'2026-11-09 02:00:00','local_label'=>'Mon','version'=>1,'settings'=>'{}','status'=>'queued','generation_id'=>(string)Str::uuid(),'created_at'=>now(),'updated_at'=>now()]);return DB::table('marketing_automation_occurrences')->find($id);
 }
 public function test_runner_completes_existing_generation_without_second_provider_attempt():void {
  $o=$this->occurrence();$run=MarketingAutomationRun::create(['id'=>$o->generation_id,'automation_id'=>$o->automation_id,'user_id'=>MarketingPostAutomation::find($o->automation_id)->user_id,'merchant_key'=>app(MarketingPosts::class)->merchant(),'status'=>'selection_completed','snapshot'=>[]]);
  $service=$this->mock(MarketingPostAutomations::class);$service->shouldReceive('view')->once()->andReturn(['status'=>'selection_completed','can_step'=>true]);$service->shouldReceive('step')->once()->andReturn(['status'=>'completed','can_step'=>false]);
  app(MarketingAutomationRunner::class)->tick(CarbonImmutable::parse('2026-11-09 02:01','UTC'));app(MarketingAutomationRunner::class)->tick(CarbonImmutable::parse('2026-11-09 02:02','UTC'));
  $this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$o->id,'status'=>'completed']);
 }
 public function test_unknown_result_is_not_resent_and_pause_cancels_unstarted_work():void {
  $o=$this->occurrence();MarketingAutomationRun::create(['id'=>$o->generation_id,'automation_id'=>$o->automation_id,'user_id'=>MarketingPostAutomation::find($o->automation_id)->user_id,'merchant_key'=>app(MarketingPosts::class)->merchant(),'status'=>'selection_unknown','snapshot'=>[]]);
  $this->mock(MarketingPostAutomations::class)->shouldReceive('view')->once()->andReturn(['status'=>'selection_unknown','can_step'=>false]);
  app(MarketingAutomationRunner::class)->tick(CarbonImmutable::parse('2026-11-09 02:01','UTC'));
  $this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$o->id,'status'=>'needs_attention']);
  $other=$this->occurrence();MarketingPostAutomation::whereKey($other->automation_id)->update(['schedule_enabled'=>false]);
  app(MarketingAutomationRunner::class)->tick(CarbonImmutable::parse('2026-11-09 02:02','UTC'));
  $this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$other->id,'status'=>'cancelled']);
 }
 public function test_cli_rejects_wrong_installation_before_any_work():void {
  config(['marketing.automation_cron_enabled'=>true]);
  $this->artisan('marketing:automation-tick',['--client'=>'wrong'])->assertExitCode(1);
  $this->assertDatabaseCount('marketing_automation_occurrences',0);
 }
 public function test_expired_unstarted_work_and_changed_version_do_not_generate():void {
  $o=$this->occurrence();$this->mock(MarketingPostAutomations::class)->shouldNotReceive('prepare');
  app(MarketingAutomationRunner::class)->tick(CarbonImmutable::parse('2026-11-09 02:31','UTC'));
  $this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$o->id,'status'=>'skipped']);
  $o=$this->occurrence();MarketingPostAutomation::whereKey($o->automation_id)->update(['version'=>2]);
  app(MarketingAutomationRunner::class)->tick(CarbonImmutable::parse('2026-11-09 02:31','UTC'));
  $this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$o->id,'status'=>'cancelled']);
 }
 public function test_pause_retains_attention_for_a_potentially_charged_run():void {
  $o=$this->occurrence();$a=MarketingPostAutomation::find($o->automation_id);$a->update(['schedule_enabled'=>false]);
  MarketingAutomationRun::create(['id'=>$o->generation_id,'automation_id'=>$o->automation_id,'user_id'=>$a->user_id,'merchant_key'=>$a->merchant_key,'status'=>'selection_requesting','snapshot'=>[]]);
  $this->mock(MarketingPostAutomations::class)->shouldReceive('view')->once()->andReturn(['status'=>'selection_requesting','can_step'=>false]);
  app(MarketingAutomationRunner::class)->tick(CarbonImmutable::parse('2026-11-09 02:01','UTC'));
  $this->assertDatabaseHas('marketing_automation_occurrences',['id'=>$o->id,'status'=>'needs_attention','reason'=>'paused_existing_generation']);
 }
}
