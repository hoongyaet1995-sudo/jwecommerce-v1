<?php
namespace Tests\Feature\Cms;
use App\Models\{User,Page,Product,StoreSetting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class LogisticsHomepageSampleTest extends TestCase
{
 use RefreshDatabase;
 public function test_sample_requires_admin_and_never_creates_pages_products_or_theme_settings(): void
 {
  $this->assertSame('sqlite',config('database.default'));
  $url='/admin/website-design/logistics-homepage-sample';
  $this->get($url)->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->get($url)->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $counts=[Page::count(),Product::count(),StoreSetting::count()];
  $response=$this->get($url.'?width=wide');
  $response->assertOk()->assertHeader('X-CMS-Sample','logistics-containers')->assertSee('cms-page-runtime',false)->assertSee('industrial-overlay')->assertSee('"containerWidth":"wide"',false);
  $this->assertSame($counts,[Page::count(),Product::count(),StoreSetting::count()]);
  $this->get($url.'?width=unbounded')->assertStatus(422);
 }
}
