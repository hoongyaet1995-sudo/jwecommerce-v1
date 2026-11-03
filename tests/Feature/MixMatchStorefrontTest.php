<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Product,PromotionOffer};
use Illuminate\Foundation\Testing\RefreshDatabase;
class MixMatchStorefrontTest extends TestCase {
 use RefreshDatabase;
 public function test_offer_page_is_public_paginated_and_expired_offer_is_hidden():void {
  $offer=PromotionOffer::create(['name'=>'Memory offer','quantity'=>3,'price'=>19900,'repeat'=>true,'enabled'=>true]);
  for($i=0;$i<21;$i++){$p=Product::create(['title'=>'Memory '.$i,'handle'=>'memory-'.$i,'base_price'=>8900,'status'=>'active','purchase_mode'=>'buy']);$offer->products()->attach($p);}
  $this->get('/offers/mix-match/'.$offer->id)->assertOk()->assertSee('Memory offer')->assertSee('Memory 19')->assertDontSee('Memory 20</',false);
  $this->get('/offers/mix-match/'.$offer->id.'?page=2')->assertOk()->assertSee('Memory 20');
  $data=app(\App\Http\Controllers\MixMatchOfferController::class)(\Illuminate\Http\Request::create('/offers/mix-match/'.$offer->id),$offer->id,app(\App\Services\Promotions\StorefrontOfferService::class))->getData();
  foreach(['tech','fashion','beauty','baby','food','home_deco','jewelry','universal','logistics','wedding'] as $theme){
   $html=view(app(\App\Services\WebsiteDesignService::class)->view('listing',null,$theme),array_replace($data,['websiteDesignTheme'=>$theme]))->render();
   $this->assertStringContainsString('Memory offer',$html);
  }
  $offer->update(['ends_at'=>now()->subMinute()]);
  $this->get('/offers/mix-match/'.$offer->id)->assertNotFound();
 }
 public function test_progress_counts_only_eligible_products_and_obeys_once_per_order():void {
  $p=Product::create(['title'=>'Memory','handle'=>'memory','base_price'=>8900,'status'=>'active','purchase_mode'=>'buy']);
  $v=$p->variants()->create(['sku'=>'MEM','title'=>'One','price'=>8900,'enabled'=>true]);
  $o=PromotionOffer::create(['name'=>'Memory offer','quantity'=>3,'price'=>19900,'repeat'=>false,'enabled'=>true]);$o->products()->attach($p);
  $rows=app(\App\Services\Promotions\StorefrontOfferService::class)->forItems(collect([['variant'=>$v,'quantity'=>4]]));
  $this->assertSame(4,$rows->first()['selected']);$this->assertSame(0,$rows->first()['remaining']);
 }
 public function test_existing_theme_checkout_and_drawer_surfaces_keep_rendering():void {
  $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
  foreach(['tech','fashion','beauty','baby','food','home_deco','jewelry','universal','logistics','wedding'] as $theme){
   $this->get('/admin/website-design/acceptance/sample/shopping-cart?theme='.$theme)->assertOk();
   $this->get('/admin/website-design/acceptance/sample/checkout?theme='.$theme)->assertOk();
  }
 }
}
