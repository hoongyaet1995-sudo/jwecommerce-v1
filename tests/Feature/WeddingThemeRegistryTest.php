<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Services\{WebsiteDesignService,WebsiteThemeCustomization,WebsiteThemeDemoData};
use App\Models\{Product,Order,StoreSetting};
class WeddingThemeRegistryTest extends TestCase
{
 use RefreshDatabase;
 public function test_registry_and_unsaved_mixed_catalog_are_theme_owned():void
 {
  $this->assertSame('sqlite',config('database.default'));
  $this->assertSame(':memory:',config('database.connections.sqlite.database'));
  $this->assertContains('wedding',WebsiteDesignService::THEMES);
  $this->assertSame('storefront.themes.wedding-checkout',app(WebsiteDesignService::class)->view('checkout',null,'wedding'));
  $catalog=app(WebsiteThemeDemoData::class)->catalog(Request::create('/demo','GET',['theme'=>'wedding']));
  $this->assertSame('wedding',$catalog['demoTheme']);
  $this->assertCount(8,$catalog['demoProducts']);
  $this->assertSame('quote',$catalog['demoProducts'][2]->purchase_mode);
  foreach($catalog['demoProducts'] as $product){$this->assertFalse($product->exists);$this->assertStringContainsString('/images/themes/wedding/',$product->images->first()->path);}
  $cart=app(WebsiteThemeDemoData::class)->cart($catalog);
  $this->assertSame(37000,$cart['summary']['subtotal']);
  $account=app(WebsiteThemeDemoData::class)->account($catalog);
  $this->assertSame('Alex',$account['user']['firstName']);
  $this->assertSame('quote',$account['wishlist'][2]['product']['purchaseMode']);
  $this->assertSame(28000,$account['orders'][0]['subtotal']);
  $uniform=app(WebsiteThemeDemoData::class)->catalog(Request::create('/demo','GET',['theme'=>'wedding','purchaseMode'=>'display','priceDisplay'=>'hide']));
  foreach($uniform['demoProducts'] as $product){$this->assertSame('display',$product->purchase_mode);$this->assertSame('hide',$product->price_display);}
  $this->assertSame(0,Product::count());$this->assertSame(0,Order::count());
 }
 public function test_media_drafts_are_independent_and_inactive_profiles_are_retained():void
 {
  $profiles=app(WebsiteThemeCustomization::class)->validate(['wedding'=>['style'=>['font'=>'wedding','colorMode'=>'custom','main'=>'#465348','sub'=>'#E5E9E1'],'media'=>['checkout'=>['desktop'=>'/images/themes/wedding/hero.png'],'checkoutSummary'=>['desktop'=>'/images/themes/wedding/album.png'],'tracking'=>['desktop'=>'/images/themes/wedding/album.png'],'trackingResult'=>['desktop'=>'/images/themes/wedding/hero.png']]],'jewelry'=>['style'=>['font'=>'editorial']]]);
  StoreSetting::updateOrCreate(['key'=>'website_design'],['value'=>['data'=>['theme'=>'default','overrides'=>[],'customization'=>$profiles]]]);
  $customizer=app(WebsiteThemeCustomization::class);
  $this->assertSame('/images/themes/wedding/hero.png',$customizer->media('wedding','checkout','fallback')['desktop']);
  $this->assertSame('/images/themes/wedding/album.png',$customizer->media('wedding','checkoutSummary','fallback')['desktop']);
  $this->assertSame('/images/themes/wedding/album.png',$customizer->media('wedding','tracking','fallback')['desktop']);
  $saved=app(WebsiteDesignService::class)->save(['theme'=>'wedding','overrides'=>[]]);
  $this->assertSame('editorial',$saved['customization']['jewelry']['style']['font']);
 }
}
