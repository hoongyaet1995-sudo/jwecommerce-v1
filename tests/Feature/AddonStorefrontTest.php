<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Product,AddonDeal};
use App\Services\Promotions\{AddonOfferService,AutomaticPromotionService,OrderPromotionAmounts};
use Illuminate\Foundation\Testing\RefreshDatabase;
class AddonStorefrontTest extends TestCase {
 use RefreshDatabase;
 private function offer():array {
  config(['inventory.current_stock_only'=>false]);
  $a=Product::create(['title'=>'Main device','handle'=>'addon-main','status'=>'active','purchase_mode'=>'buy','base_price'=>10000]);
  $b=Product::create(['title'=>'Extra device','handle'=>'addon-extra','status'=>'active','purchase_mode'=>'buy','base_price'=>2000]);
  $main=$a->variants()->create(['sku'=>'ADDMAIN','title'=>'Main option','enabled'=>true,'price'=>10000,'stock'=>10]);
  $extra=$b->variants()->create(['sku'=>'ADDEXTRA','title'=>'Extra option','enabled'=>true,'price'=>2000,'stock'=>10]);
  $deal=AddonDeal::create(['name'=>'Workspace extras','enabled'=>true,'per_order_cap'=>1,'product_ids'=>[$a->id],'addons'=>[['product_id'=>$b->id,'price'=>1000]]]);
  return [$a,$main,$extra,$deal];
 }
 private function data($main,$extra,$deal):array{return ['offer_id'=>$deal->id,'main_variant_id'=>$main->id,'addon_variant_id'=>$extra->id,'expected_price'=>'1000'];}
 public function test_drawer_reminder_and_actual_saving_belong_to_their_own_lines():void {
  [$product,$main,$extra,$deal]=$this->offer();
  $items=collect([['variant'=>$main,'quantity'=>1],['variant'=>$extra,'quantity'=>1]]);
  $offers=app(AddonOfferService::class)->cartOffers($items);
  $snapshot=['type'=>'addon','allocations'=>[(string)$extra->id=>1000]];
  $render=fn($variant,$snapshot)=>view('storefront.partials.addon-cart-item',['variant'=>$variant,'product'=>$variant->product,'drawerAddonOffers'=>$offers,'summary'=>['promotion_snapshot'=>$snapshot]])->render();
  $mainHtml=$render($main,$snapshot);$extraHtml=$render($extra,$snapshot);
  $this->assertStringContainsString('Choose add-on',$mainHtml);
  $this->assertStringContainsString('#addon-deal-'.$deal->id,$mainHtml);
  $this->assertStringNotContainsString('Add-on savings',$mainHtml);
  $this->assertStringContainsString('Add-on savings',$extraHtml);
  $this->assertStringNotContainsString('Choose add-on',$extraHtml);
  $this->assertStringNotContainsString('Add-on savings',$render($extra,['type'=>'mix_match','allocations'=>[(string)$extra->id=>1000]]));
 }
 public function test_explicit_add_adds_both_atomically_and_reuses_existing_main():void {
  [$p,$main,$extra,$deal]=$this->offer();
  $this->post('/cart/addon',$this->data($main,$extra,$deal))->assertRedirect('/cart')->assertSessionHas('cart',[$main->id=>1,$extra->id=>1]);
  $this->post('/cart/addon',$this->data($main,$extra,$deal))->assertSessionHasErrors('offer')->assertSessionHas('cart',[$main->id=>1,$extra->id=>1]);
  $this->withSession(['cart'=>[$main->id=>2],'addon_selections'=>[]])->post('/cart/addon',$this->data($main,$extra,$deal))->assertSessionHas('cart',[$main->id=>2,$extra->id=>1]);
 }
 public function test_expiry_changed_price_and_stock_do_not_partially_add():void {
  [$p,$main,$extra,$deal]=$this->offer();$extra->update(['stock'=>0]);
  $this->post('/cart/addon',$this->data($main,$extra,$deal))->assertSessionHasErrors('offer')->assertSessionMissing('cart');
  $extra->update(['stock'=>10]);$deal->update(['addons'=>[['product_id'=>$extra->product_id,'price'=>1100]]]);
  $this->post('/cart/addon',$this->data($main,$extra,$deal))->assertSessionHasErrors('offer')->assertSessionMissing('cart');
  $deal->update(['ends_at'=>now()->subMinute()]);
  $this->post('/cart/addon',$this->data($main,$extra,$deal))->assertSessionHasErrors('offer');
 }
 public function test_shared_pricing_requires_selection_main_and_current_price_and_preserves_net_lines():void {
  [$p,$main,$extra,$deal]=$this->offer();$items=collect([['variant'=>$main,'quantity'=>1],['variant'=>$extra,'quantity'=>2]]);$service=app(AddonOfferService::class);
  $this->assertSame(0,$service->quote($items,[])['discount']);
  $selected=[$deal->id=>[$extra->id=>['price'=>1000,'quantity'=>2]]];$quote=$service->quote($items,$selected);
  $this->assertSame(1000,$quote['discount']);$this->assertSame(0,$service->quote($items->slice(1),$selected)['discount']);
  $snapshot=app(OrderPromotionAmounts::class)->snapshot(['promotion_snapshot'=>$quote,'discount'=>1000,'tax'=>0],$items);
  $this->assertSame(10000,$snapshot['lines'][$main->id]['net']);$this->assertSame(3000,$snapshot['lines'][$extra->id]['net']);
  $deal->update(['enabled'=>false]);$this->assertSame(0,$service->quote($items,$selected)['discount']);
 }
 public function test_pdp_component_renders_all_existing_themes_without_draft_leaks():void {
  [$p,$main,$extra,$deal]=$this->offer();
  $response=$this->get('/products/'.$p->handle)->assertOk()->assertSee($deal->name)->assertSee('Extra device');
  $data=app(\App\Http\Controllers\StorefrontController::class)->show(\Illuminate\Http\Request::create('/products/'.$p->handle),$p->handle)->getData();
  foreach(['tech','fashion','beauty','baby','food','home_deco','jewelry','universal','logistics','wedding'] as $theme){
   $html=view(app(\App\Services\WebsiteDesignService::class)->view('pdp',null,$theme),array_replace($data,['websiteDesignTheme'=>$theme]))->render();
   $this->assertStringContainsString('data-addon-offer',$html,$theme);
  }
  $deal->update(['enabled'=>false]);$this->get('/products/'.$p->handle)->assertOk()->assertDontSee('data-addon-offer',false);
 }
}
