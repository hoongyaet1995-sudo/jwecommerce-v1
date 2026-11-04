<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Promotions\TieredCalculator;
class TieredDiscountTest extends TestCase {
 use \Illuminate\Foundation\Testing\RefreshDatabase;
 public function test_highest_tier_and_exact_minor_unit_allocations():void {
  $c=new TieredCalculator;
  $tiers=[['spend'=>10000,'discount'=>1000],['spend'=>20000,'discount'=>2500]];
  $this->assertSame(0,$c->calculate([['key'=>'1','unit_price'=>9999,'quantity'=>1]],$tiers,'fixed')['discount']);
  $r=$c->calculate([['key'=>'1','unit_price'=>10001,'quantity'=>1],['key'=>'2','unit_price'=>9999,'quantity'=>1]],$tiers,'fixed');
  $this->assertSame(2500,$r['discount']);$this->assertSame(2500,array_sum($r['allocations']));
  $r=$c->calculate([['key'=>'1','unit_price'=>10005,'quantity'=>1]],[['spend'=>10000,'discount'=>10]],'percentage');
  $this->assertSame(1001,$r['discount']);
 }

 private function fixture():array {
 $p=\App\Models\Product::create(['title'=>'Tier item','handle'=>'tier-item','status'=>'active','purchase_mode'=>'buy','base_price'=>10000]);
 $v=$p->variants()->create(['sku'=>'TIER-1','title'=>'Tier item','enabled'=>true,'price'=>10000,'stock'=>20]);
 $o=\App\Models\TieredDiscount::create(['name'=>'Spend and save','enabled'=>true,'scope'=>'selected','discount_type'=>'fixed','product_ids'=>[$p->id],'tiers'=>[['spend'=>10000,'discount'=>1000],['spend'=>20000,'discount'=>2500]]]);return [$p,$v,$o];
 }
 public function test_scope_availability_competition_and_historical_allocations():void {
 [$p,$v,$o]=$this->fixture();$items=collect([['variant'=>$v,'quantity'=>2]]);$svc=app(\App\Services\Promotions\AutomaticPromotionService::class);
 $q=$svc->quote($items);$this->assertSame('tiered',$q['type']);$this->assertSame(2500,$q['discount']);
 $snap=app(\App\Services\Promotions\OrderPromotionAmounts::class)->snapshot(['promotion_snapshot'=>$q,'discount'=>2500,'tax'=>0],$items);$this->assertSame(17500,$snap['lines'][$v->id]['net']);
 $order=new \App\Models\Order;$order->promotion_snapshot=$snap;$item=new \App\Models\OrderItem;$item->product_variant_id=$v->id;
 $a=app(\App\Services\Promotions\OrderPromotionAmounts::class);$this->assertSame(17500,$a->itemAmount($order,$item,1)+$a->itemAmount($order,$item,1,1));
 $o->update(['product_ids'=>[]]);$this->assertSame(0,$svc->quote($items)['discount']);$o->update(['scope'=>'all']);$this->assertSame(2500,$svc->quote($items)['discount']);
 $mix=\App\Models\PromotionOffer::create(['name'=>'Better mix','enabled'=>true,'quantity'=>2,'price'=>15000,'repeat'=>false]);$mix->products()->sync([$p->id]);$this->assertSame('mix_match',$svc->quote($items)['type']);$mix->update(['enabled'=>false]);
 $o->update(['ends_at'=>now()->subMinute()]);$this->assertSame(0,$svc->quote($items)['discount']);$o->update(['ends_at'=>null,'starts_at'=>now()->addDay()]);$this->assertSame(0,$svc->quote($items)['discount']);
 $this->assertSame(17500,$snap['lines'][$v->id]['net']);
 }
 public function test_admin_validation_and_access():void {
 [$p,$v,$o]=$this->fixture();$payload=['name'=>'New tier','enabled'=>false,'scope'=>'selected','discount_type'=>'percentage','product_ids'=>[$p->id],'tiers'=>[['spend'=>10000,'discount'=>10]]];
 $this->postJson('/admin/api/tiered-discounts',$payload)->assertUnauthorized();$this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
 $this->postJson('/admin/api/tiered-discounts',$payload)->assertCreated();$this->patchJson('/admin/api/tiered-discounts/'.$o->id,$payload)->assertOk();
 $this->postJson('/admin/api/tiered-discounts',[...$payload,'tiers'=>[['spend'=>10000,'discount'=>101]]])->assertUnprocessable();
 $this->postJson('/admin/api/tiered-discounts',[...$payload,'product_ids'=>[]])->assertUnprocessable();
 $this->postJson('/admin/api/tiered-discounts',[...$payload,'tiers'=>[['spend'=>10000,'discount'=>10],['spend'=>10000,'discount'=>20]]])->assertUnprocessable();
 }
 public function test_all_themes_and_chevron_render():void {
 [$p,$v,$o]=$this->fixture();config(['inventory.current_stock_only'=>false]);
 $data=app(\App\Http\Controllers\StorefrontController::class)->show(\Illuminate\Http\Request::create('/products/'.$p->handle),$p->handle)->getData();
 foreach(['tech','fashion','beauty','baby','food','home_deco','jewelry','universal','logistics','wedding'] as $theme){$html=view(app(\App\Services\WebsiteDesignService::class)->view('pdp',null,$theme),array_replace($data,['websiteDesignTheme'=>$theme]))->render();$this->assertStringContainsString('data-tiered-product',$html,$theme);$this->assertStringContainsString('tiered-details',$html);}
 $this->withSession(['cart'=>[$v->id=>1]])->get('/cart')->assertOk();$r=\Illuminate\Http\Request::create('/cart');$r->setLaravelSession(app('session.store'));$data=app(\App\Services\CartViewData::class)->for($r);
 foreach(['tech','fashion','beauty','baby','food','home-deco','jewelry','universal','logistics','wedding'] as $theme){$html=view('storefront.themes.'.$theme.'-cart',$data)->render();$this->assertStringContainsString('data-tiered-progress',$html,$theme);}
 }
}
