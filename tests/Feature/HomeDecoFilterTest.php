<?php
namespace Tests\Feature;
use App\Services\WebsiteDesignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HomeDecoFilterTest extends TestCase {
 use RefreshDatabase;
 public function test_theme_filter_preserves_query_and_exposes_accessible_price_inputs():void {
  app(WebsiteDesignService::class)->save(['theme'=>'home_deco','overrides'=>[]]);
  $this->get('/shop?search=oak&sort=price_asc&min_price=1900&max_price=27900')->assertOk()->assertSee('data-home-deco-filters',false)->assertSee('aria-label="Minimum price amount"',false)->assertSee('name="search" value="oak"',false)->assertSee('name="sort" value="price_asc"',false);
 }
}
