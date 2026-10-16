<?php
namespace Tests\Feature;

use App\Models\{User,Product,Order};
use App\Services\WebsiteThemeDemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteThemeDemoTest extends TestCase
{
    use RefreshDatabase;
    public function test_all_food_acceptance_scenarios_render_without_creating_store_records(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach(WebsiteThemeDemoData::SCENARIOS as $scenario) {
            $this->get('/admin/website-design/acceptance/sample/'.$scenario)->assertOk()->assertHeader('X-Theme-Data','dummy');
        }
        $this->assertSame(0,Product::count());
        $this->assertSame(0,Order::count());
    }
    public function test_all_home_scenarios_use_industry_data_and_do_not_write_records(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach(array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']) as $scenario) {
            $this->get('/admin/website-design/acceptance/sample/'.$scenario.'?theme=home_deco')->assertOk()->assertHeader('X-Theme-Data','dummy')->assertDontSee('PANTRY10')->assertDontSee('Tuscan Extra Virgin Olive Oil');
        }
        $this->get('/admin/website-design/acceptance?theme=home_deco')->assertOk()->assertSee('Home &amp; Deco',false);
        $this->assertSame(0,Product::count());$this->assertSame(0,Order::count());
    }
    public function test_demo_catalog_does_not_read_real_catalog_and_stock_override_requires_object_identity(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        Product::create(['title'=>'Private catalog product','handle'=>'private','status'=>'active','base_price'=>1]);
        $this->get('/admin/website-design/preview?theme=food&surface=plp')->assertOk()->assertSee('Tuscan Extra Virgin Olive Oil')->assertDontSee('Private catalog product')->assertSee('__JW_THEME_DEMO__',false);
        $request=request(); $data=app(WebsiteThemeDemoData::class)->catalog($request);
        $variant=$data['demoProducts']->first()->variants->first();
        $this->assertFalse($variant->exists);
        $this->assertSame(25,app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant));
        config(['inventory.current_stock_only'=>true]);
        $this->assertNull(app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity(clone $variant));
        $variant->exists=true;
        $this->assertNull(app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant));
    }
    public function test_available_themes_render_fixed_catalog_and_drawer(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach(['default','home_deco','beauty','food'] as $theme) {
            foreach(['plp','pdp','login','account'] as $surface) $this->get('/admin/website-design/preview?theme='.$theme.'&surface='.$surface)->assertOk();
            $this->get('/admin/website-design/cart-drawer?theme='.$theme)->assertOk();
        }
    }
    public function test_quote_checkout_demos_use_theme_renderers_and_never_create_commerce_records(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach (['food','home_deco','beauty','tech','baby','jewelry','fashion','universal'] as $theme) {
            $this->get('/admin/website-design/acceptance/sample/quotation-checkout?theme='.$theme)
                ->assertOk()->assertHeader('X-Theme-Data','dummy')->assertSee('data-quotation-checkout',false)
                ->assertSee('__JW_THEME_DEMO__',false)->assertDontSee('data-checkout-shipping-quote-url',false);
        }
        foreach (['products','orders','order_payments','enquiry_quotations','enquiry_quotation_versions','quotation_acceptances'] as $table) {
            $this->assertDatabaseCount($table,0);
        }
    }

    public function test_acceptance_workspace_is_admin_protected(): void
    {
        $this->get('/admin/website-design/acceptance')->assertRedirect();
        $this->get('/admin/website-design/acceptance/sample/plp')->assertRedirect();
    }
}
