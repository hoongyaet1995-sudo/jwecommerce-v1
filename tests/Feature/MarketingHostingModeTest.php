<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
class MarketingHostingModeTest extends TestCase {
 use RefreshDatabase;
 public function test_shared_host_blocks_execution_before_validation_or_queueing():void{
  config(['marketing.execution_enabled'=>false]);$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));\Illuminate\Support\Facades\Queue::fake();$id=fake()->uuid();foreach(['/admin/marketing/templates/projects/'.$id.'/plan','/admin/marketing/templates/projects/'.$id.'/planning-authorize','/admin/marketing/workspace/'.$id.'/creative'] as $url)$this->postJson($url,[])->assertStatus(423)->assertJsonPath('code','marketing_execution_unavailable');\Illuminate\Support\Facades\Queue::assertNothingPushed();
 }
 public function test_shared_host_home_is_available_with_explicit_execution_notice():void{
  config(['marketing.execution_enabled'=>false]);$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]))->get('/admin/marketing')->assertOk()->assertSee('Production is unavailable');
 }
}
