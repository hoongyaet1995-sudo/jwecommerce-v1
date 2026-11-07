<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use App\Models\User;
class MarketingAutomationPageTest extends TestCase {
 use RefreshDatabase;
 public function test_automation_entry_and_empty_settings_do_not_generate_or_schedule(){Http::preventStrayRequests();Queue::fake();$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->get('/admin/marketing/automation')->assertOk()->assertSee('data-studio-nav="automation"',false)->assertSee('AI Post Generation')->assertSee(url('/admin/marketing/automation/ai-posts'))->assertDontSee('data-studio-nav="posts"',false);$this->get('/admin/marketing/automation/ai-posts')->assertOk()->assertSee('No automations yet')->assertSee('New automations are paused by default.')->assertSee('Create automation');$this->assertDatabaseCount('marketing_daily_draft_runs',0);$this->assertDatabaseCount('marketing_posts',0);Http::assertNothingSent();Queue::assertNothingPushed();}
 public function test_automation_pages_require_marketing_access(){Http::preventStrayRequests();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$this->actingAs($u)->get('/admin/marketing/automation')->assertForbidden();$this->get('/admin/marketing/automation/ai-posts')->assertForbidden();$u->permissions()->create(['permission'=>'marketing.view']);$this->get('/admin/marketing/automation')->assertOk();Http::assertNothingSent();}
}
