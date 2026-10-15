<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Product,Order};
use App\Services\{WebsiteDesignService,WebsiteThemeCustomization,WebsiteThemeDemoData};
class FashionThemePackTest extends TestCase
{
 use RefreshDatabase;
 public function test_theme_and_capabilities_are_registered():void {
  $this->assertContains('fashion',WebsiteDesignService::THEMES);
  $slots=app(WebsiteThemeCustomization::class)->mediaContracts()['fashion'];
  $this->assertSame(['checkoutSuccess'],$slots['checkoutSuccess']);
  $this->assertArrayNotHasKey('checkout',$slots);
 }
 public function test_all_scenes_use_fashion_renderers_without_database_business_writes():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  foreach(array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']) as $scene){
   $this->get('/admin/website-design/acceptance/sample/'.$scene.'?theme=fashion')->assertOk()->assertHeader('X-Theme-Data','dummy');
  }
  $this->assertSame(0,Product::count());$this->assertSame(0,Order::count());
 }
 public function test_public_override_loads_fashion_css_and_canonical_width():void {
  $category=\App\Models\Category::create(['name'=>'Fashion','slug'=>'fashion','is_active'=>true]);
  $product=Product::create(['category_id'=>$category->id,'handle'=>'luna','title'=>'Luna Pearl Pendant','status'=>'active','base_price'=>18900]);
  \App\Models\StoreSetting::updateOrCreate(['key'=>'website_design'],['value'=>['data'=>['theme'=>'baby','overrides'=>['category:'.$category->id=>'fashion']]]]);
  foreach(['/categories/fashion','/products/luna'] as $url)$this->get($url)->assertOk()->assertSee('/css/fashion-theme.css',false)->assertSee('fs-theme',false);
 }
 public function test_gallery_is_sequential_and_optional_row_disappears_with_two_images():void {
  $request=\Illuminate\Http\Request::create('/demo','GET',['theme'=>'fashion']);
  $catalog=app(WebsiteThemeDemoData::class)->catalog($request);$product=$catalog['demoProducts']->first();
  request()->attributes->set('websiteDesignPreview','fashion');
  request()->attributes->set('websiteThemeDemoVariants',$product->variants->all());
  $render=function()use($product){return view('storefront.themes.fashion-pdp',['product'=>$product,'images'=>$product->images,'selectedImage'=>$product->images->first(),'selectedVariant'=>$product->variants->first(),'pdpBrand'=>'Sample','pdpPaymentMethods'=>[],'companions'=>collect(),'websiteDesignPreview'=>true])->render();};
  $html=$render();$this->assertStringContainsString('fs-gallery-extra',$html);
  $product->setRelation('images',$product->images->take(2));
  $this->assertStringNotContainsString('class="fs-gallery-extra"',$render());
  $product->setRelation('images',$product->images->take(1));
  $this->assertStringNotContainsString('Enlarge detail image',$render());
 }
 public function test_style_and_media_drafts_use_declared_controls_and_do_not_save():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $profile=['fashion'=>['style'=>['font'=>'editorial','size'=>'big','colorMode'=>'custom','main'=>'#567466','sub'=>'#dae4da'],'media'=>['checkoutSuccess'=>['hidden'=>true,'mobile'=>'/images/themes/fashion/detail.png']]]];
  $this->get('/admin/website-design/acceptance/sample/checkout-success?theme=fashion&customization='.urlencode(json_encode($profile)))->assertOk()->assertSee('#567466',false)->assertDontSee('class="jr-hero"',false);
  $this->assertDatabaseMissing('store_settings',['key'=>'website_design']);
  $this->expectException(\Illuminate\Validation\ValidationException::class);
  app(WebsiteThemeCustomization::class)->validate(['fashion'=>['media'=>['checkout'=>['desktop'=>'/wrong-slot.png']]]]);
 }
}
