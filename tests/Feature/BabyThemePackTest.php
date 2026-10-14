<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Product,Order};
use App\Services\WebsiteThemeDemoData;
class BabyThemePackTest extends TestCase {
 use RefreshDatabase;
 public function test_all_baby_scenes_render_without_store_writes():void {
 $this->actingAs(User::factory()->create(['is_admin'=>true]));
 foreach(array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']) as $scene){$this->get('/admin/website-design/acceptance/sample/'.$scene.'?theme=baby')->assertOk()->assertHeader('X-Theme-Data','dummy');}
 $this->assertSame(0,Product::count());$this->assertSame(0,Order::count());
 }
 public function test_registration_preserves_optional_phone_and_requires_consent():void {
 $this->post('/account/register',['name'=>'Sample Parent','email'=>'parent@example.test','password'=>'SamplePassword123','password_confirmation'=>'SamplePassword123','phone'=>'0123456789','terms'=>'1'])->assertRedirect();
 $this->assertDatabaseHas('customers',['email'=>'parent@example.test','phone'=>'0123456789']);
 }
 public function test_registration_without_terms_is_rejected():void {
 $this->post('/account/register',['name'=>'No Consent','email'=>'no-consent@example.test','password'=>'SamplePassword123','password_confirmation'=>'SamplePassword123'])->assertSessionHasErrors('terms');
 $this->assertDatabaseMissing('customers',['email'=>'no-consent@example.test']);
 }
 public function test_baby_media_capabilities_exclude_checkout_decoration():void {
 $slots=app(\App\Services\WebsiteThemeCustomization::class)->mediaContracts()['baby'];
 $this->assertArrayNotHasKey('checkout',$slots);$this->assertSame(['cart'],$slots['cart']);
 $this->actingAs(User::factory()->create(['is_admin'=>true]));
 $this->get('/admin/website-design/preview?theme=baby&surface=login')->assertOk()->assertSee('Hello again.')->assertSee('baby/background.png',false);
 $this->get('/admin/website-design/acceptance/sample/checkout?theme=baby')->assertOk()->assertDontSee('data-checkout-page-media',false)->assertSee('Delivery Method');
 }
 public function test_baby_catalog_inherits_each_shared_header_footer_width():void {
 $this->actingAs(User::factory()->create(['is_admin'=>true]));
 foreach(['standard','wide','full'] as $width){
 $setting=\App\Models\StorefrontShellSetting::query()->where('key','global')->first() ?? new \App\Models\StorefrontShellSetting();
 $setting->key='global';$setting->payload=['contentWidth'=>$width];$setting->save();
 $this->get('/admin/website-design/acceptance/sample/plp?theme=baby')->assertOk()->assertSee('data-storefront-width="'.$width.'"',false);
 }
 }
 public function test_public_catalog_override_loads_the_rendered_baby_styles_with_a_different_store_theme():void {
 $category=\App\Models\Category::create(['name'=>'Baby & Kids','slug'=>'baby-kids','description'=>'Soft textures for little moments.','is_active'=>true]);
 $collection=\App\Models\Collection::create(['slug'=>'little-essentials','title'=>'Little essentials','is_active'=>true,'type'=>'manual','status'=>'active']);
 $product=Product::create(['category_id'=>$category->id,'handle'=>'baby-blanket','title'=>'Cloud Cotton Muslin Blanket','status'=>'active','base_price'=>8900]);
 $collection->products()->attach($product);
 \App\Models\StoreSetting::updateOrCreate(['key'=>'website_design'],['value'=>['data'=>['theme'=>'tech','overrides'=>['category:'.$category->id=>'baby','collection:'.$collection->id=>'baby']]]]);
 foreach(['/categories/baby-kids','/collections/little-essentials','/products/baby-blanket'] as $url){
 $this->get($url)->assertOk()->assertSee('/css/baby-theme.css',false)->assertSee('bk-theme',false);
 }
 $this->get('/categories/baby-kids')->assertDontSee('class="tech-theme bk-theme"',false)->assertSee('data-website-theme="baby"',false);
 }
}
