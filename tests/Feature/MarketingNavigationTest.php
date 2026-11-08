<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use App\Models\{User,MarketingProject};
class MarketingNavigationTest extends TestCase {
 use RefreshDatabase;
 public function test_owner_can_view_live_navigation_entries_without_jobs_or_provider_calls():void{
  Http::fake();Queue::fake();$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  foreach(['/admin/marketing','/admin/marketing/campaigns','/admin/marketing/posts','/admin/marketing/calendar','/admin/marketing/analytics','/admin/marketing/content','/admin/marketing/channels','/admin/marketing/settings','/admin/marketing/video-studio'] as $url){$r=$this->get($url)->assertOk()->assertSee('Content Library')->assertSee('Video Studio');foreach(['campaigns','calendar','analytics','content','channels'] as $page)$r->assertSee(url('/admin/marketing/'.$page));}
  $this->get('/admin/marketing/content/templates')->assertRedirect(route('marketing.templates.entry'));$this->assertDatabaseCount('marketing_projects',0);$this->assertDatabaseCount('marketing_ai_runs',0);Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_preview_access_requires_marketing_view_permission():void{
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$this->actingAs($u)->get('/admin/marketing/campaigns')->assertForbidden();$u->permissions()->create(['permission'=>'marketing.view']);$this->get('/admin/marketing/campaigns')->assertOk();$this->postJson('/admin/marketing/campaigns',[])->assertStatus(405);
 }
 public function test_existing_video_list_keeps_real_projects_and_complete_navigation():void{
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$p=MarketingProject::create(['owner_id'=>$u->id,'source_type'=>'theme','name'=>'Preserved original video','snapshot'=>['title'=>'Original theme','type'=>'theme'],'brief'=>[],'version'=>1]);
  $this->actingAs($u)->get('/admin/marketing/projects')->assertOk()->assertSee($p->name)->assertSee('Campaigns')->assertSee(url('/admin/marketing/content'))->assertDontSee('November collection launch');$this->assertSame(1,$p->fresh()->version);$this->assertDatabaseCount('marketing_projects',1);
 }
}
