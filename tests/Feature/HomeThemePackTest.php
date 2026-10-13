<?php
namespace Tests\Feature;
use App\Models\{User,Product,StoreSetting};
use App\Services\{WebsiteDesignService,WebsiteThemeCustomization};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HomeThemePackTest extends TestCase
{
 use RefreshDatabase;
 public function test_public_routes_select_home_bodies_without_changing_shell_settings():void {
  StoreSetting::updateOrCreate(['key'=>'storefront_shell'],['value'=>['data'=>['test'=>'preserved']]]);$before=StoreSetting::where('key','storefront_shell')->value('value');
  app(WebsiteDesignService::class)->save(['theme'=>'home_deco','overrides'=>[]]);
  $this->get('/cart')->assertOk()->assertSee('data-home-deco-cart-page',false);
  $this->get('/order-tracking')->assertOk()->assertSee('Follow your pieces home.')->assertSee('data-home-deco-tracking',false);
  $p=Product::create(['title'=>'Owned chair','handle'=>'owned-chair','status'=>'active','base_price'=>10000]);$v=$p->variants()->create(['sku'=>'HOME-TEST','title'=>'Cream','price'=>10000,'stock'=>0,'enabled'=>true]);
  $this->withSession(['cart'=>[$v->id=>1]])->get('/checkout')->assertOk()->assertSee('data-home-deco-checkout',false)->assertSee('data-checkout-shipping-quote-url',false)->assertSee('name="country"',false);
  $this->assertSame($before,StoreSetting::where('key','storefront_shell')->value('value'));
 }
 public function test_home_media_and_style_reach_draft_and_saved_consumers():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $profile=['home_deco'=>['style'=>['font'=>'modern','size'=>'big','colorMode'=>'custom','main'=>'#112233','sub'=>'#ddeeff'],'media'=>['checkout'=>['desktop'=>'/checkout-image.png','mobile'=>'/checkout-mobile.png','focus'=>'right','focusY'=>'bottom'],'checkoutSuccess'=>['desktop'=>'/success-image.png'],'tracking'=>['desktop'=>'/tracking-image.png']]]];
  $this->putJson('/admin/api/website-design',['theme'=>'home_deco','overrides'=>[],'customization'=>$profile])->assertOk()->assertJsonPath('mediaContracts.home_deco.account',[]);
  foreach(['checkout'=>'/checkout-image.png','checkoutSuccess'=>'/success-image.png','tracking'=>'/tracking-image.png'] as $surface=>$image)$this->get('/admin/website-design/preview?theme=home_deco&surface='.$surface)->assertOk()->assertSee($image,false)->assertSee('#112233',false)->assertSee('DM Sans',false);
  $profile['home_deco']['media']['checkout']['hidden']=true;
  $this->get('/admin/website-design/preview?'.http_build_query(['theme'=>'home_deco','surface'=>'checkout','customization'=>json_encode($profile)]))->assertOk()->assertDontSee('data-checkout-page-media',false);
  $this->assertFalse(app(WebsiteDesignService::class)->live()['customization']['home_deco']['media']['checkout']['hidden']??false);
 }
 public function test_pending_proof_uses_canonical_upload_field():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get('/admin/website-design/acceptance/sample/payment-pending?theme=home_deco')->assertOk()->assertSee('name="payment_proof"',false)->assertDontSee('name="proof"',false)->assertDontSee('Payment received');
 }
 public function test_account_copy_and_home_gallery_actions_follow_approved_contract():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get('/admin/website-design/acceptance/sample/account-orders?theme=home_deco')->assertOk()->assertSee('MY ACCOUNT')->assertSee('Your home, collected.')->assertDontSee('Welcome back')->assertDontSee('View your orders and their latest status.')->assertDontSee('Carrier:')->assertDontSee('Review your purchase')->assertDontSee('Exceptional flavour');
  $this->get('/admin/website-design/acceptance/sample/quick-buy?theme=home_deco')->assertOk()->assertSee('data-home-deco-quick-buy',false)->assertSee('data-home-quick-checkout',false)->assertSee('Natural oak')->assertSee('Dark oak')->assertSee('90 cm')->assertSee('110 cm');
  $this->get('/admin/website-design/preview?theme=home_deco&surface=checkout')->assertOk()->assertDontSee('Bank transfer instructions')->assertDontSee('Select to prefill details');
 }
}
