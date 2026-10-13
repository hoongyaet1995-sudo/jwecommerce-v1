<?php
namespace Tests\Feature;
use App\Models\{User,Product,Order,StoreSetting};
use App\Services\{WebsiteDesignService,WebsiteThemeDemoData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TechnologyThemePackTest extends TestCase
{
 use RefreshDatabase;
 public function test_cart_checkout_and_company_logo_follow_approved_contract():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  StoreSetting::updateOrCreate(['key'=>'shop_profile'],['value'=>['data'=>['shopLogo'=>'/company-logo.png']]]);
  $this->get('/admin/website-design/acceptance/sample/shopping-cart?theme=tech')->assertOk()
   ->assertSee('Your cart')->assertSee('tc-grid',false)->assertSee('tc-summary',false)->assertSee('tc-art',false)
   ->assertSee('DEMO-TECH-0')->assertSee('RM 1,398.00')->assertDontSee('ritual')->assertDontSee('skincare');
  $this->get('/admin/website-design/acceptance/sample/checkout?theme=tech')->assertOk()
   ->assertSee('Contact information')->assertSee('Shipping address')->assertSee('tco-summary',false)->assertDontSee('Complete your ritual');
  $this->get('/admin/website-design/acceptance/sample/order-tracking-verify-code?theme=tech')->assertOk()
   ->assertSee('/company-logo.png',false)->assertSee('Verify your email');
  $request=\Illuminate\Http\Request::create('/admin/website-design/preview?theme=tech');
  $catalog=app(\App\Services\TechThemeDemoData::class)->catalog($request);
  $this->assertSame(6,$catalog['products']->total());
  $this->assertFalse($catalog['filters']['priceRequested']);
 }
 public function test_all_scenes_use_isolated_technology_data():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $before=[Product::count(),Order::count(),User::count()];
  $this->get('/admin/website-design/acceptance?theme=tech')->assertOk()->assertSee('Technology &amp; Electronics',false);
  foreach(array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']) as $scene)
   $this->get('/admin/website-design/acceptance/sample/'.$scene.'?theme=tech')->assertOk()->assertHeader('X-Theme-Data','dummy')->assertSee('__JW_THEME_DEMO__',false)->assertDontSee('Daily Serum')->assertDontSee('Tuscan Extra Virgin Olive Oil');
  $this->assertSame($before,[Product::count(),Order::count(),User::count()]);
 }
 public function test_media_styles_and_public_routes_preserve_shell():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $profile=['tech'=>['style'=>['font'=>'modern','size'=>'small','colorMode'=>'custom','main'=>'#1254dd','sub'=>'#eef6ff'],'media'=>['account'=>['desktop'=>'/account-tech.png'],'checkout'=>['desktop'=>'/checkout-tech.png'],'checkoutSuccess'=>['desktop'=>'/success-tech.png'],'tracking'=>['desktop'=>'/tracking-tech.png'],'allProducts'=>['desktop'=>'/shop-tech.png']]]];
  $this->putJson('/admin/api/website-design',['theme'=>'tech','overrides'=>[],'customization'=>$profile])->assertOk();
  foreach(['account'=>'/account-tech.png','checkout'=>'/checkout-tech.png','checkoutSuccess'=>'/success-tech.png','tracking'=>'/tracking-tech.png','allProducts'=>'/shop-tech.png'] as $surface=>$image)
   $this->get('/admin/website-design/preview?theme=tech&surface='.$surface)->assertOk()->assertSee($image,false)->assertSee('#1254dd',false);
  $this->get('/account/login')->assertOk()->assertSee('data-tech-auth',false);
  $this->get('/cart')->assertOk()->assertSee('data-tech-cart-page',false);
  $this->get('/order-tracking')->assertOk()->assertSee('data-tech-tracking',false);
  $this->get('/admin/website-design/acceptance/sample/payment-pending?theme=tech')->assertOk()->assertSee('name="payment_proof"',false);
  $this->get('/admin/website-design/acceptance/sample/checkout-success?theme=tech')->assertOk()->assertDontSee('name="payment_proof"',false);
 }
}
