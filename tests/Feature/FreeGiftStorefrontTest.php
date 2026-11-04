<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,Product,AddonDeal};
use App\Services\Promotions\{FreeGiftOfferService,OrderPromotionAmounts};
use Illuminate\Foundation\Testing\RefreshDatabase;
class FreeGiftStorefrontTest extends TestCase {
 use RefreshDatabase;
 private function offer():array {
 config(['inventory.current_stock_only'=>false]);
 $a=Product::create(['title'=>'Main device','handle'=>'gift-main','status'=>'active','purchase_mode'=>'buy','base_price'=>10000]);
 $b=Product::create(['title'=>'Gift device','handle'=>'gift-extra','status'=>'active','purchase_mode'=>'buy','base_price'=>2000]);
 $m=$a->variants()->create(['sku'=>'GMAIN','title'=>'Main','enabled'=>true,'price'=>10000,'stock'=>10]);
 $g=$b->variants()->create(['sku'=>'GGIFT','title'=>'Gift','enabled'=>true,'price'=>2000,'stock'=>10]);
 $d=AddonDeal::create(['kind'=>'gift','name'=>'Buy two get one','enabled'=>true,'buy_quantity'=>2,'gift_quantity'=>1,'per_order_cap'=>1,'product_ids'=>[$a->id],'addons'=>[['product_id'=>$b->id,'price'=>0]]]);return [$a,$m,$g,$d];
 }
 public function test_explicit_claim_adds_required_mains_and_caps_gifts():void {
 [$p,$m,$g,$d]=$this->offer();$data=['offer_id'=>$d->id,'main_variant_id'=>$m->id,'gift_variant_id'=>$g->id];
 $this->post('/cart/free-gift',$data)->assertRedirect('/cart')->assertSessionHas('cart',[$m->id=>2,$g->id=>1]);
 $this->post('/cart/free-gift',$data)->assertSessionHasErrors('offer')->assertSessionHas('cart',[$m->id=>2,$g->id=>1]);
 $this->get('/cart')->assertOk()->assertSee('Free gift');
 }
 public function test_main_reduction_and_expiry_remove_gifts_not_charge_regular_price():void {
 [$p,$m,$g,$d]=$this->offer();
 $this->withSession(['cart'=>[$m->id=>1,$g->id=>1],'gift_selections'=>[$d->id=>[$g->id=>1]]])->get('/cart')->assertOk()->assertSessionHas('cart',[$m->id=>1]);
 $d->update(['enabled'=>false]);
 $this->withSession(['cart'=>[$m->id=>2,$g->id=>1],'gift_selections'=>[$d->id=>[$g->id=>1]]])->get('/cart')->assertOk()->assertSessionHas('cart',[$m->id=>2]);
 }
 public function test_quote_and_snapshot_keep_free_line_zero():void {
 [$p,$m,$g,$d]=$this->offer();$items=collect([['variant'=>$m,'quantity'=>2],['variant'=>$g,'quantity'=>1]]);$service=app(FreeGiftOfferService::class);
 $this->assertSame(0,$service->quote($items,[])['discount']);
 $q=$service->quote($items,[$d->id=>[$g->id=>1]]);$this->assertSame(2000,$q['discount']);
 $s=app(OrderPromotionAmounts::class)->snapshot(['promotion_snapshot'=>$q,'discount'=>2000,'tax'=>0],$items);$this->assertSame(0,$s['lines'][$g->id]['net']);$this->assertSame(20000,$s['lines'][$m->id]['net']);
 }
 public function test_reminders_hide_only_after_the_same_offers_slots_are_filled():void {
 [$p,$m,$g,$d]=$this->offer();$d->update(['per_order_cap'=>2]);$svc=app(FreeGiftOfferService::class);
 $items=collect([['variant'=>$m,'quantity'=>2],['variant'=>$g,'quantity'=>1]]);
 $snapshot=['type'=>'gift','offer_id'=>$d->id,'allocations'=>[$g->id=>2000]];
 $this->assertFalse($svc->hasRemainingSlots($d,$items,$snapshot));
 $html=view('storefront.partials.addon-cart-item',['product'=>$p,'variant'=>$m,'items'=>$items,'summary'=>['promotion_snapshot'=>$snapshot]])->render();
 $this->assertStringNotContainsString('Choose gift',$html);
 $this->assertTrue($svc->hasRemainingSlots($d,$items,[]));
 $this->assertTrue($svc->hasRemainingSlots($d,$items,[...$snapshot,'offer_id'=>$d->id+1]));
 $items[0]=['variant'=>$m,'quantity'=>4];$this->assertTrue($svc->hasRemainingSlots($d,$items,$snapshot));
 $snapshot['allocations'][$g->id]=4000;$this->assertFalse($svc->hasRemainingSlots($d,$items,$snapshot));
 $addon=\App\Models\AddonDeal::create(['kind'=>'addon','name'=>'Extra','enabled'=>true,'per_order_cap'=>1,'product_ids'=>[$p->id],'addons'=>[['product_id'=>$g->product_id,'price'=>500]]]);
 $a=app(\App\Services\Promotions\AddonOfferService::class);
 $this->assertFalse($a->hasRemainingSlots($addon,$items,['type'=>'addon','offer_id'=>$addon->id,'allocations'=>[$g->id=>1500]]));
 $this->assertTrue($a->hasRemainingSlots($addon,$items,$snapshot));
 }
 public function test_admin_gift_rules_and_addon_isolation():void {
 [$p,$m,$g,$d]=$this->offer();$this->actingAs(User::factory()->create(['is_admin'=>true]));
 $this->getJson('/admin/api/free-gift-deals')->assertOk()->assertJsonPath('data.0.kind','gift');
 $this->getJson('/admin/api/addon-deals')->assertOk()->assertJsonCount(0,'data');
 $payload=['name'=>'Gift rule','buy_quantity'=>1,'gift_quantity'=>1,'per_order_cap'=>2,'product_ids'=>[$p->id],'addons'=>[['product_id'=>$g->product_id,'price'=>0]]];
 $this->postJson('/admin/api/free-gift-deals',$payload)->assertCreated();
 $this->patchJson('/admin/api/addon-deals/'.$d->id,$payload)->assertNotFound();
 $this->postJson('/admin/api/free-gift-deals',[...$payload,'buy_quantity'=>0])->assertUnprocessable();
 }
 public function test_all_pdp_themes_render_gifts_and_keep_addons_separate():void {
 [$p,$m,$g,$d]=$this->offer();$data=app(\App\Http\Controllers\StorefrontController::class)->show(\Illuminate\Http\Request::create('/products/'.$p->handle),$p->handle)->getData();
 foreach(['tech','fashion','beauty','baby','food','home_deco','jewelry','universal','logistics','wedding'] as $theme){$html=view(app(\App\Services\WebsiteDesignService::class)->view('pdp',null,$theme),array_replace($data,['websiteDesignTheme'=>$theme]))->render();$this->assertStringContainsString('Choose gift',$html,$theme);$this->assertStringContainsString('Buy two get one',$html,$theme);}
 }
 public function test_all_cart_themes_include_item_promotions():void {
 [$p,$m,$g,$d]=$this->offer();
 $this->withSession(['cart'=>[$m->id=>2,$g->id=>1],'gift_selections'=>[$d->id=>[$g->id=>1]]])->get('/cart')->assertOk();
 $request=\Illuminate\Http\Request::create('/cart');$request->setLaravelSession(app('session.store'));
 $data=app(\App\Services\CartViewData::class)->for($request);
 $data['items']=collect([['variant'=>$m,'quantity'=>2,'available'=>10],['variant'=>$g,'quantity'=>1,'available'=>10]]);
 $data['summary']['promotion_snapshot']=['type'=>'gift','offer_id'=>$d->id,'allocations'=>[$g->id=>2000]];
 foreach(['tech','fashion','beauty','baby','food','home-deco','jewelry','universal','logistics','wedding'] as $theme){
 $html=view('storefront.themes.'.$theme.'-cart',$data)->render();
 $this->assertStringContainsString('cart-item-addon-saving',$html,$theme);
 $this->assertStringNotContainsString('Choose gift',$html,$theme);
 }
 }
}
