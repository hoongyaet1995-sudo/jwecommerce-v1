<?php
namespace Tests\Feature;
use App\Models\{User,Product,StoreSetting};
use App\Services\{WebsiteDesignService,WebsiteThemeDemoData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BeautyThemePackTest extends TestCase
{
 use RefreshDatabase;
 public function test_real_customer_account_accepts_coupon_payload_without_demo_only_fields():void {
  app(WebsiteDesignService::class)->save(['theme'=>'beauty','overrides'=>[]]);
  $user=User::factory()->create(['is_admin'=>false]);
  \App\Models\Customer::create(['user_id'=>$user->id,'email'=>$user->email,'first_name'=>'Alex']);
  \App\Models\Coupon::create(['code'=>'REAL10','name'=>'Real coupon','discount_type'=>'percentage','value'=>10,'is_active'=>true]);
  $this->actingAs($user,'customer')->get('/account')->assertOk()->assertSee('REAL10')->assertSee('data-beauty-account',false);
 }
 public function test_beauty_acceptance_has_industry_data_without_database_writes():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $before=[Product::count(),\App\Models\Order::count(),User::count()];
  $this->get('/admin/website-design/acceptance?theme=beauty')->assertOk()->assertSee('Beauty &amp; Skincare',false);
  foreach(array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']) as $scene){
   $this->get('/admin/website-design/acceptance/sample/'.$scene.'?theme=beauty')->assertOk()->assertHeader('X-Theme-Data','dummy')->assertDontSee('Tuscan Extra Virgin Olive Oil')->assertDontSee('Oak Ridge Coffee Table')->assertSee('__JW_THEME_DEMO__',false);
  }
  $this->assertSame($before,[Product::count(),\App\Models\Order::count(),User::count()]);
 }
 public function test_beauty_media_style_and_payment_proof_contracts():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $profile=['beauty'=>['style'=>['font'=>'editorial','size'=>'small','colorMode'=>'custom','main'=>'#442244','sub'=>'#eeddee'],'media'=>['account'=>['desktop'=>'/overview.png'],'checkout'=>['desktop'=>'/checkout.png'],'checkoutSuccess'=>['desktop'=>'/success.png','mobile'=>'/success-mobile.png'],'tracking'=>['desktop'=>'/tracking.png']]]];
  $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profile])->assertOk();
  foreach(['account'=>'/overview.png','checkout'=>'/checkout.png','checkoutSuccess'=>'/success.png','tracking'=>'/tracking.png'] as $surface=>$image)$this->get('/admin/website-design/preview?theme=beauty&surface='.$surface)->assertOk()->assertSee($image,false)->assertSee('#442244',false)->assertSee('Lora',false);
  $this->get('/admin/website-design/acceptance/sample/payment-pending?theme=beauty')->assertOk()->assertSee('name="payment_proof"',false)->assertDontSee('Your ritual, confirmed.');
  $this->get('/admin/website-design/acceptance/sample/checkout-success?theme=beauty')->assertOk()->assertSee('Your ritual, confirmed.')->assertDontSee('name="payment_proof"',false);
  $profile['beauty']['media']['checkout']['hidden']=true;
  $this->get('/admin/website-design/preview?'.http_build_query(['theme'=>'beauty','surface'=>'checkout','customization'=>json_encode($profile)]))->assertOk()->assertDontSee('data-checkout-page-media',false);
 }
 public function test_public_beauty_route_selection_preserves_shell():void {
  StoreSetting::updateOrCreate(['key'=>'storefront_shell'],['value'=>['data'=>['preserve'=>'shell']]]);$before=StoreSetting::where('key','storefront_shell')->value('value');
  app(WebsiteDesignService::class)->save(['theme'=>'beauty','overrides'=>[]]);
  $this->get('/cart')->assertOk()->assertSee('data-beauty-cart-page',false);
  $this->get('/order-tracking')->assertOk()->assertSee('Follow your ritual.');
  $this->get('/account/login')->assertOk()->assertSee('data-beauty-auth',false);
  $this->assertSame($before,StoreSetting::where('key','storefront_shell')->value('value'));
 }
}
