<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Product,Order,StoreSetting};
use App\Services\{WebsiteDesignService,WebsiteThemeCustomization,WebsiteThemeDemoData};
class UniversalThemePackTest extends TestCase
{
 use RefreshDatabase;
 public function test_registration_and_capability_contract():void {
  $this->assertContains('universal',WebsiteDesignService::THEMES);
  $slots=app(WebsiteThemeCustomization::class)->mediaContracts()['universal'];
  foreach(['allProducts','account','login','register','forgot','reset','tracking','checkoutSuccess'] as $surface)$this->assertNotEmpty($slots[$surface]);
  $this->assertArrayNotHasKey('checkout',$slots);
  $profile=app(WebsiteThemeCustomization::class)->validate(['universal'=>['style'=>['font'=>'universal','colorMode'=>'custom','main'=>'#2457E6','sub'=>'#EDF2FF']]]);
  $this->assertSame('universal',$profile['universal']['style']['font']);
 }
 public function test_all_scenes_render_without_business_database_writes():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  foreach(array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']) as $scene){$this->get('/admin/website-design/acceptance/sample/'.$scene.'?theme=universal')->assertOk()->assertHeader('X-Theme-Data','dummy');}
  $this->assertSame(0,Product::count());$this->assertSame(0,Order::count());
 }
 public function test_draft_styles_media_do_not_mutate_live_settings():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $p=['universal'=>['style'=>['font'=>'universal','colorMode'=>'custom','main'=>'#563799','sub'=>'#eeeaff'],'media'=>['login'=>['mobile'=>'/images/test-mobile.png']]]];
  $this->get('/admin/website-design/acceptance/sample/login?theme=universal&customization='.urlencode(json_encode($p)))->assertOk()->assertSee('#563799',false)->assertSee('/images/test-mobile.png',false);
  $this->assertDatabaseMissing('store_settings',['key'=>'website_design']);
 }
 public function test_price_filter_uses_minor_units_and_limits_results():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $request=\Illuminate\Http\Request::create('/demo','GET',['theme'=>'universal','min_price'=>5000,'max_price'=>10000]);
  $catalog=app(WebsiteThemeDemoData::class)->catalog($request);
  $titles=$catalog['products']->pluck('title')->all();$this->assertContains('Everyday Bottle',$titles);$this->assertNotContains('Canvas Carry Tote',$titles);
 }
 public function test_pending_never_claims_payment_is_paid():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get('/admin/website-design/acceptance/sample/payment-pending?theme=universal')->assertOk()->assertSee('Awaiting payment')->assertDontSee('Payment confirmed');
 }
}
