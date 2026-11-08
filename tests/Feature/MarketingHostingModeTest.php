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
 public function test_avatar_management_works_without_enabling_generation():void {
  config(['marketing.execution_enabled'=>false]);\Illuminate\Support\Facades\Storage::fake('local');\Illuminate\Support\Facades\Http::preventStrayRequests();\Illuminate\Support\Facades\Queue::fake();
  $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($owner);
  $this->post(route('marketing.avatars.draft'),['request_id'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'Host reference','kind'=>'synthetic'])->assertSessionHasNoErrors()->assertRedirect();
  $a=\App\Models\MarketingAvatar::firstOrFail();$this->assertSame('draft',$a->status);
  $this->post(route('marketing.avatars.write',[$a->id,'reference']),['file'=>\Illuminate\Http\UploadedFile::fake()->image('host.png',300,400),'consent'=>1])->assertSessionHasNoErrors()->assertRedirect();
  $this->post(route('marketing.avatars.write',[$a->id,'publish']),['request_id'=>(string)\Illuminate\Support\Str::uuid(),'checked'=>1])->assertSessionHasNoErrors()->assertRedirect();$this->assertSame('ready',$a->fresh()->status);
  foreach(['archive'=>'archived','restore'=>'ready','revoke'=>'revoked'] as $action=>$state){$this->post(route('marketing.avatars.write',[$a->id,$action]))->assertSessionHasNoErrors()->assertRedirect();$this->assertSame($state,$a->fresh()->status);}
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]))->post(route('marketing.avatars.write',[$a->id,'archive']))->assertNotFound();
  $this->assertFalse(config('marketing.execution_enabled'));\Illuminate\Support\Facades\Http::assertNothingSent();\Illuminate\Support\Facades\Queue::assertNothingPushed();
 }

}
