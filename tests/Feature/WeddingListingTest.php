<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Product,Category,StoreSetting};
use App\Services\CatalogFilterService;
use Illuminate\Http\Request;
final class WeddingListingTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void { parent::setUp(); config(['product_enquiries.enabled'=>true]); }
 private function item(string $handle,string $mode,int $price):Product {
  $p=Product::create(['title'=>$handle,'handle'=>$handle,'status'=>'active','purchase_mode'=>$mode,'price_display'=>$mode==='quote'?'hide':'exact','base_price'=>$price]);
  $p->variants()->create(['sku'=>strtoupper($handle),'title'=>'Standard','price'=>$price,'stock'=>5,'enabled'=>true,'color_name'=>$mode==='quote'?'Secret colour':'Ivory']);return $p;
 }
 private function catalogResult(array $query=[]){$request=Request::create('/shop','GET',$query);$request->attributes->set('websiteDesignPreview','wedding');app()->instance('request',$request);return app(CatalogFilterService::class)->for($request,Product::query()->where('status','active'));}
 public function test_quote_costs_and_inventory_facets_are_not_disclosed():void {
  $this->item('quote-only','quote',987654321);
  $r=$this->catalogResult(['in_stock'=>1,'min_price'=>987654321,'price_filter'=>1]);
  $this->assertFalse($r->facets['hasPricedProducts']);$this->assertSame(['min'=>0,'max'=>0],$r->facets['priceBounds']);$this->assertSame([],$r->facets['colors']);$this->assertFalse($r->filters['inStock']);$this->assertFalse($r->filters['priceRequested']);$this->assertSame(1,$r->products->total());
 }
 public function test_mixed_catalog_bounds_and_price_filter_use_buy_products_only():void {
  $buy=$this->item('album','buy',28000);$this->item('quote','quote',987654321);$this->item('display','display',555555);
  $r=$this->catalogResult();$this->assertSame(['min'=>28000,'max'=>28000],$r->facets['priceBounds']);$this->assertSame(['Ivory'],array_column($r->facets['colors'],'value'));
  $r=$this->catalogResult(['min_price'=>28000,'max_price'=>28000,'price_filter'=>1]);$this->assertSame([$buy->id],$r->products->pluck('id')->all());
 }
 public function test_wedding_pagination_is_three_stable_and_other_themes_keep_twelve():void {
  for($i=0;$i<7;$i++)$this->item('album-'.$i,'buy',28000);
  $first=$this->catalogResult(['sort'=>'price_asc']);$second=$this->catalogResult(['sort'=>'price_asc','page'=>2]);$third=$this->catalogResult(['sort'=>'price_asc','page'=>3]);
  $this->assertSame(3,$first->products->perPage());$this->assertCount(3,$first->products);$this->assertCount(3,$second->products);$this->assertCount(1,$third->products);$this->assertSame([],array_intersect($first->products->pluck('id')->all(),$second->products->pluck('id')->all()));$this->assertStringContainsString('sort=price_asc',$first->products->nextPageUrl());
  $request=Request::create('/shop');$request->attributes->set('websiteDesignPreview','jewelry');app()->instance('request',$request);$other=app(CatalogFilterService::class)->for($request,Product::query());$this->assertSame(12,$other->products->perPage());
 }
 public function test_demo_batches_facets_and_quote_gallery_match_public_contract():void {
  $demo=app(\App\Services\WeddingThemeDemoData::class);
  $a=$demo->catalog(Request::create('/admin/website-design/preview','GET',['theme'=>'wedding','sort'=>'price_asc','page'=>1]));
  $b=$demo->catalog(Request::create('/admin/website-design/preview','GET',['theme'=>'wedding','sort'=>'price_asc','page'=>2]));
  $this->assertSame(3,$a['products']->perPage());$this->assertCount(3,$a['products']);$this->assertCount(3,$b['products']);
  $this->assertSame([],array_intersect($a['products']->pluck('id')->all(),$b['products']->pluck('id')->all()));
  $this->assertSame(['min'=>9000,'max'=>35000],$a['facets']['priceBounds']);$this->assertTrue($a['facets']['hasPricedProducts']);
  $this->assertStringContainsString('sort=price_asc',$a['products']->nextPageUrl());$this->assertCount(4,$a['demoProducts'][2]->images);
  $quote=$demo->catalog(Request::create('/demo','GET',['theme'=>'wedding','purchaseMode'=>'quote','priceDisplay'=>'hide','in_stock'=>1]));
  $this->assertSame(['min'=>0,'max'=>0],$quote['facets']['priceBounds']);$this->assertFalse($quote['facets']['hasPricedProducts']);$this->assertFalse($quote['filters']['inStock']);$this->assertSame(8,$quote['products']->total());
 }
 public function test_listing_description_and_closing_content_have_canonical_owners():void {
  $product=$this->item('description-owner','buy',28000);$product->update(['subtitle'=>'SHORT TAGLINE ONLY','description'=>'<p>REAL PRODUCT DESCRIPTION</p>']);
  StoreSetting::create(['key'=>'website_design','value'=>['data'=>['theme'=>'wedding','customization'=>['wedding'=>['content'=>['allProducts'=>['main'=>['text'=>'Custom closing heading'],'sub'=>['hidden'=>true]]]]]]]]);
  $response=$this->get('/shop')->assertOk();$html=$response->getContent();
  $this->assertStringContainsString('class="wd-listing-description">REAL PRODUCT DESCRIPTION',$html);
  $this->assertStringNotContainsString('class="wd-listing-description">SHORT TAGLINE ONLY',$html);
  $response->assertSee('Custom closing heading')->assertDontSee('Explore the collections above.');
 }
 public function test_public_wedding_lists_real_mode_actions_and_hides_quote_price_controls():void {
  $this->item('real-quote','quote',987654321);StoreSetting::create(['key'=>'website_design','value'=>['data'=>['theme'=>'wedding']]]);
  $this->get('/shop')->assertOk()->assertSee('data-wedding-listing',false)->assertSee('Price on request')->assertSee(route('enquiries.create','real-quote'),false)->assertDontSee('987654321')->assertDontSee('Minimum price amount')->assertDontSee('In-stock products only')->assertSee('wedding-listing.js');
 }
}
