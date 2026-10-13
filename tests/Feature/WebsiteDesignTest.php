<?php
namespace Tests\Feature;
use App\Models\{User,Category,Collection};
use App\Services\WebsiteDesignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class WebsiteDesignTest extends TestCase
{
    use RefreshDatabase;
    public function test_design_round_trip_inheritance_and_reset(): void
    {
        $category=Category::create(['name'=>'Tables','slug'=>'design-tables','is_active'=>true]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $settings=['theme'=>'home_deco','overrides'=>['category:'.$category->id=>'default']];
        $this->putJson('/admin/api/website-design',$settings)->assertOk()->assertJsonPath('settings.theme','home_deco');
        $this->getJson('/admin/api/website-design')->assertOk()->assertJsonPath('settings.overrides.category:'.$category->id,'default');
        $service=app(WebsiteDesignService::class);
        $this->assertSame('default',$service->resolve('category:'.$category->id));
        $this->assertSame('home_deco',$service->resolve());
        $this->get('/categories/design-tables')->assertOk()->assertViewIs('storefront.index');
        $this->putJson('/admin/api/website-design',['theme'=>'home_deco','overrides'=>[]])->assertOk();
        $this->assertSame('home_deco',$service->resolve('category:'.$category->id));
        $this->get('/categories/design-tables')->assertOk()->assertSee('data-website-theme="home_deco"',false)->assertDontSee('id="categories"',false);
    }
    public function test_unavailable_themes_and_invalid_scopes_are_rejected():void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->putJson('/admin/api/website-design',['theme'=>'technology','overrides'=>[]])->assertUnprocessable();
        $this->putJson('/admin/api/website-design',['theme'=>'home_deco','overrides'=>['category:999999'=>'home_deco']])->assertUnprocessable();
        $this->get('/admin/website-design/preview?theme=technology')->assertUnprocessable();
    }
    public function test_collection_override_retains_the_original_renderer_and_can_follow_store(): void
    {
        $collection=Collection::create(['title'=>'Living room','slug'=>'design-living','type'=>'manual','is_active'=>true,'status'=>'published']);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $scope='collection:'.$collection->id;
        $this->putJson('/admin/api/website-design',['theme'=>'home_deco','overrides'=>[$scope=>'default']])->assertOk();
        $this->get('/collections/design-living')->assertOk()->assertViewIs('storefront.index');
        $this->putJson('/admin/api/website-design',['theme'=>'home_deco','overrides'=>[]])->assertOk();
        $this->get('/collections/design-living')->assertOk()->assertViewIs('storefront.themes.home-deco-listing')->assertSee('No products match your selection.');
    }
    public function test_preview_is_draft_only_and_requires_admin():void
    {
        $this->getJson('/admin/api/website-design')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->get('/admin/website-design/preview?theme=home_deco')->assertOk()->assertSee('data-website-theme="home_deco"',false)->assertSee('id="cart-drawer"',false)->assertSee('data-atelier-theme-root',false);
        $this->assertSame('default',app(WebsiteDesignService::class)->resolve());
    }
}
