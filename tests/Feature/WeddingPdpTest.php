<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Category,Product,User,Order};
use App\Services\WebsiteDesignService;
class WeddingPdpTest extends TestCase
{
 use RefreshDatabase;
 private function product(string $mode='quote',string $display='hide'):Product
 {
  config(['product_enquiries.enabled'=>true]);
  app(WebsiteDesignService::class)->save(['theme'=>'wedding','overrides'=>[]]);
  $category=Category::firstOrCreate(['slug'=>'wedding-story'],['name'=>'Wedding stories','is_active'=>true]);
  $p=Product::create(['title'=>'The Wedding Day '.$mode,'handle'=>'wedding-'.$mode,'category_id'=>$category->id,'status'=>'active','purchase_mode'=>$mode,'price_display'=>$display,'base_price'=>987654,'subtitle'=>'A real merchant subtitle','description'=>'<p>Saved merchant description</p>']);
  $p->variants()->create(['title'=>'Full day','sku'=>'WEDDING-'.$mode,'price'=>987654,'stock'=>73919,'enabled'=>true]);
  foreach(['first','second','third'] as $i=>$name)$p->images()->create(['path'=>'/images/'.$name.'-wedding.jpg','alt'=>$name.' photo','position'=>$i]);
  $p->pdpContent()->create(['story'=>['heading'=>'Our saved story','description'=>'Story from the merchant','image'=>'/images/saved-story.jpg'],'specifications'=>[['label'=>'Coverage','value'=>'Eight hours']],'custom_accordions'=>[['title'=>'Saved collection details','content'=>'Merchant details']]]);
  return $p;
 }
 public function test_quote_pdp_reads_saved_content_and_ordered_gallery_without_price_stock_or_purchase_action():void
 {
  $this->assertSame('sqlite',config('database.default'));$this->assertSame(':memory:',config('database.connections.sqlite.database'));
  $p=$this->product();
  $related=Product::create(['title'=>'Related real collection','handle'=>'related-real','status'=>'active','purchase_mode'=>'display','base_price'=>0]);
  $p->pdpRelatedProducts()->create(['related_product_id'=>$related->id,'sort_order'=>0]);
  $before=[Product::count(),Order::count()];
  $r=$this->get('/products/'.$p->handle)->assertOk()->assertSee('data-wedding-pdp',false)->assertSee('Wedding stories')->assertSee('A real merchant subtitle')->assertSee('Saved merchant description')->assertSee('Our saved story')->assertSee('Related real collection')->assertSee('Eight hours')->assertSee('Price on request')->assertSee('data-product-enquiry',false)->assertDontSee('data-pdp-add-to-cart',false)->assertDontSee('987654')->assertDontSee('73919');
  $html=$r->getContent();preg_match('/data-pdp-hero-image[^>]*src="([^"]+)"/',$html,$hero);$this->assertSame('/images/first-wedding.jpg',$hero[1]??null);
  $this->assertStringContainsString('class="wd-pdp-marquee"',$html);
  $marquee=explode('class="wd-pdp-marquee"',$html)[1];$marquee=explode('</section>',$marquee)[0];
  $this->assertStringContainsString('/images/second-wedding.jpg',$marquee);$this->assertStringNotContainsString('/images/first-wedding.jpg',$marquee);
  $this->assertSame($before,[Product::count(),Order::count()]);
 }
 public function test_buy_and_display_actions_follow_shared_mode_contract():void
 {
  $buy=$this->product('buy','exact');$this->get('/products/'.$buy->handle)->assertOk()->assertSee('data-pdp-add-to-cart',false)->assertSee('name="quantity"',false)->assertDontSee('data-product-enquiry',false);
  $display=$this->product('display','hide');$this->get('/products/'.$display->handle)->assertOk()->assertDontSee('data-pdp-add-to-cart',false)->assertDontSee('data-product-enquiry',false)->assertDontSee('987654')->assertDontSee('73919');
 }
 public function test_protected_demo_pdp_renders_without_creating_catalog():void
 {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get('/admin/website-design/acceptance/sample/pdp?theme=wedding&purchaseMode=quote&priceDisplay=hide')->assertOk()->assertSee('data-wedding-pdp',false)->assertSee('Price on request')->assertSee('The Wedding Day')->assertHeader('X-Theme-Data','dummy');
  $this->assertSame(0,Product::count());$this->assertSame(0,Order::count());
 }
 public function test_quick_view_variant_price_keeps_shared_from_label():void
 {
  $product=$this->product('quote','from')->load(['images','variants','category']);
  $html=view('storefront.themes.wedding-quick-view',['product'=>$product,'websiteDesignPreview'=>true])->render();
  $this->assertGreaterThanOrEqual(2,substr_count($html,'From '));
  $this->assertStringNotContainsString('73919',$html);
 }

 public function test_quick_quote_exposes_only_enabled_options_and_owns_single_variant():void
 {
  $product=$this->product('quote','hide');
  $enabled=$product->variants()->first();
  $disabled=$product->variants()->create(['title'=>'Disabled private option','sku'=>'DISABLED-OPTION','price'=>11111,'enabled'=>false]);
  $html=view('storefront.themes.wedding-quick-view',['product'=>$product->load(['images','variants','category']),'websiteDesignPreview'=>false,'errors'=>new \Illuminate\Support\ViewErrorBag()])->render();
  $this->assertStringNotContainsString('Disabled private option',$html);
  $this->assertStringContainsString('type="hidden" data-enquiry-quick-variant value="'.$enabled->id.'"',$html);
  $this->assertStringContainsString('data-sku="'.$enabled->sku.'"',$html);
  $this->assertStringContainsString('name="enquiry_context" value="product"',$html);
  $product->variants()->create(['title'=>'Second enabled option','sku'=>'ENABLED-SECOND','price'=>11111,'enabled'=>true]);
  $html=view('storefront.themes.wedding-quick-view',['product'=>$product->load('variants'),'websiteDesignPreview'=>false,'errors'=>new \Illuminate\Support\ViewErrorBag()])->render();
  $this->assertStringContainsString('data-enquiry-quick-variant><option',$html);
  $this->assertStringContainsString('data-sku="ENABLED-SECOND"',$html);
  $this->assertStringNotContainsString('Disabled private option',$html);
 }
 public function test_lazy_quick_enquiry_validation_recovers_on_canonical_product_with_old_input():void
 {
  $product=$this->product('quote','hide');$variant=$product->variants()->first();
  $payload=['enquiry_context'=>'product','product_id'=>$product->id,'variant_id'=>$variant->id,'request_key'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'Saved visitor','email'=>'invalid-email','requirements'=>'My saved wedding plans'];
  $this->from('/shop?page=2')->post('/enquiries',$payload)->assertRedirect(route('storefront.products.show',['handle'=>$product->handle,'variant'=>$variant->id]))->assertSessionHasErrors(['email'],null,'enquiry')->assertSessionHasInput('requirements','My saved wedding plans');
  $this->get(route('storefront.products.show',['handle'=>$product->handle,'variant'=>$variant->id]))->assertOk()->assertSee('data-reopen',false)->assertSee('My saved wedding plans')->assertSee('Saved visitor');
  $payload['email']='valid@example.test';$payload['variant_id']=999999;
  $this->from('/shop?page=2')->post('/enquiries',$payload)->assertRedirect(route('storefront.products.show',$product->handle))->assertSessionHasErrors(['variant_id'],null,'enquiry');
  $this->assertSame(0,\App\Models\ProductEnquiry::count());
 }

}
