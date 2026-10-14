<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Services\WebsiteDesignService;
class CartDrawerLayoutTest extends TestCase
{
 use RefreshDatabase;
 public function test_every_theme_keeps_promo_in_footer_outside_product_scroll_region():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  foreach(WebsiteDesignService::THEMES as $theme){
   $response=$this->get('/admin/website-design/cart-drawer?theme='.$theme)->assertOk();
   $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());$xpath=new \DOMXPath($dom);
   $this->assertSame(1,$xpath->query('//*[@id="cart-drawer-content"]/footer/details[contains(@class,"cart-drawer-promo")]')->length,$theme);
   $this->assertSame(0,$xpath->query('//*[@data-cart-drawer-lines]//details[contains(@class,"cart-drawer-promo")]')->length,$theme);
   $this->assertSame(1,$xpath->query('//details[contains(@class,"cart-drawer-promo")]/summary/svg')->length,$theme);
   $this->assertSame(1,$xpath->query('//details[contains(@class,"cart-drawer-promo")]/form[@data-cart-drawer-form]/input[@name="code"]')->length,$theme);
   $response->assertSee(route('cart.coupon.apply'),false);
   if(in_array($theme,['baby','jewelry'],true))$this->assertSame(0,$xpath->query('//*[@id="cart-drawer-content"]/header//svg[not(contains(@class,"lucide"))]')->length,$theme);
  }
 }
 public function test_coupon_error_remains_visible_and_expands_the_footer_form():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->withSession(['coupon_error'=>'This code is unavailable.'])->get('/admin/website-design/cart-drawer?theme=jewelry')->assertOk()->assertSee('This code is unavailable.')->assertSee('role="alert"',false)->assertSee('class="cart-drawer-promo"  open',false);
 }
}
