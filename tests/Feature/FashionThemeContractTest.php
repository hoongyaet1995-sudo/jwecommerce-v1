<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\{WebsiteDesignService,WebsiteThemeCustomization};
use Illuminate\Validation\ValidationException;

class FashionThemeContractTest extends TestCase
{
 use \Illuminate\Foundation\Testing\RefreshDatabase;
 public function test_fashion_contract_accepts_named_content_and_rejects_unsupported_slots():void {
  $this->assertContains('fashion',WebsiteDesignService::THEMES);
  $service=app(WebsiteThemeCustomization::class);
  $this->assertSame(['main','sub'],array_keys($service->contentContracts()['fashion']['cart']));
  $data=$service->validate(['fashion'=>['style'=>['font'=>'street','main'=>'#c6f432','sub'=>'#d9dce0'],'content'=>['cart'=>['main'=>['text'=>'Wear your own story.','hidden'=>false]]]]]);
  $this->assertSame('Wear your own story.',$data['fashion']['content']['cart']['main']['text']);
  $this->expectException(ValidationException::class);
  $service->validate(['fashion'=>['content'=>['pdp'=>['main'=>['text'=>'Unsupported']]]]]);
 }
 public function test_hidden_and_plain_text_content_resolves_without_merchant_defaults():void {
  request()->attributes->set('websiteThemeCustomization',['fashion'=>['content'=>['cart'=>['main'=>['text'=>'<script>alert(1)</script>','hidden'=>false],'sub'=>['text'=>'Hidden','hidden'=>true]]]]]);
  $service=app(WebsiteThemeCustomization::class);
  $this->assertSame('<script>alert(1)</script>',$service->content('fashion','cart')['main']);
  $this->assertArrayNotHasKey('sub',$service->content('fashion','cart'));
  $this->assertSame([],$service->content('fashion','checkout'));
 }
 public function test_content_save_draft_and_escaped_renderer_share_contract():void {
  $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
  $profile=['fashion'=>['content'=>['cart'=>['main'=>['text'=>'<script>alert(1)</script>','hidden'=>false]]]]];
  $this->get('/admin/website-design/acceptance/sample/shopping-cart?theme=fashion&customization='.urlencode(json_encode($profile)))
   ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
  $this->assertDatabaseMissing('store_settings',['key'=>'website_design']);
  $this->putJson('/admin/api/website-design',['theme'=>'fashion','overrides'=>[],'customization'=>$profile])->assertOk();
  $this->assertSame($profile,app(WebsiteDesignService::class)->live()['customization']);
  $this->putJson('/admin/api/website-design',['theme'=>'fashion','overrides'=>[],'customization'=>['fashion'=>['content'=>['cart'=>['main'=>['text'=>str_repeat('x',161)]]]]]])->assertStatus(422);
 }
 public function test_checkout_retains_shipping_quote_hooks():void {
  $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
  foreach(['fashion','jewelry'] as $theme)$this->get('/admin/website-design/acceptance/sample/checkout?theme='.$theme)
   ->assertOk()->assertSee('data-checkout-shipping-quote-url=',false)->assertDontSee('quofs-url',false)->assertDontSee('quojg-url',false);
 }
}
