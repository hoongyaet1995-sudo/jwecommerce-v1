<?php
namespace Tests\Feature;
use App\Models\{StoreSetting,User,Customer,Product};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BeautyThemePackTest extends TestCase
{
 use RefreshDatabase;
 public function test_beauty_theme_roundtrip_and_all_customer_surfaces(): void
 {
  $service=app(\App\Services\WebsiteDesignService::class);
  $service->save(['theme'=>'beauty','overrides'=>[]]);
  $this->assertSame('beauty',$service->resolve());
  foreach(['/account/login','/account/register','/account/forgot-password'] as $url) $this->get($url)->assertOk()->assertSee('data-beauty-auth',false);
  $this->get('/account/reset-password/test-token?email=sample%40example.com')->assertOk()->assertSee('data-beauty-auth="reset"',false)->assertSee('name="token" value="test-token"',false);
  $p=Product::create(['title'=>'Real catalog item','handle'=>'beauty-test','status'=>'active','base_price'=>4900]);
  $p->variants()->create(['sku'=>'BEAUTY-TEST','title'=>'Option','price'=>4900,'stock'=>0,'enabled'=>true]);
  $this->get('/shop')->assertOk()->assertSee('data-website-theme="beauty"',false)->assertSee('Real catalog item');
  $this->get('/products/beauty-test')->assertOk()->assertSee('data-beauty-pdp',false)->assertSee('Awaiting stock verification');
  $this->get('/cart/drawer')->assertOk()->assertSee('data-beauty-cart',false);
  $u=User::factory()->create(['is_admin'=>false]);Customer::create(['user_id'=>$u->id,'email'=>$u->email,'first_name'=>'Buyer']);
  $this->actingAs($u,'customer')->get('/account')->assertOk()->assertSee('data-beauty-account',false)->assertDontSee('1250 PTS');
  $service->save(['theme'=>'default','overrides'=>[]]);
  $this->get('/shop')->assertOk()->assertDontSee('data-website-theme="beauty"',false);
 }
 public function test_beauty_admin_previews_are_disabled_and_do_not_save_settings(): void
 {
  $u=User::factory()->create(['is_admin'=>true]);$this->actingAs($u);
  foreach(['login','register','forgot','account'] as $surface) $this->get('/admin/website-design/preview?theme=beauty&surface='.$surface)->assertOk()->assertSee('disabled',false);
  $this->get('/admin/website-design/preview?theme=beauty&surface=account')->assertDontSee($u->email)->assertSee('sample@example.com');
  $this->assertSame('default',app(\App\Services\WebsiteDesignService::class)->resolve());
 }
 public function test_category_override_and_draft_filters_preserve_theme_context(): void
 {
  $category=\App\Models\Category::create(['name'=>'Care','slug'=>'care','is_active'=>true]);
  $service=app(\App\Services\WebsiteDesignService::class);
  $service->save(['theme'=>'default','overrides'=>['category:'.$category->id=>'beauty']]);
  $this->get('/categories/care')->assertOk()->assertViewIs('storefront.themes.beauty-listing');
  $p=Product::create(['title'=>'Care item','handle'=>'care-item','status'=>'active','base_price'=>4900,'category_id'=>$category->id]);
  $p->variants()->create(['sku'=>'CARE','title'=>'Option','price'=>4900,'stock'=>0,'enabled'=>true]);
  $this->get('/products/care-item')->assertOk()->assertViewIs('storefront.themes.beauty-pdp');
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get('/admin/website-design/preview?theme=beauty&scope=shop&category=care&sort=price_asc&min_price=1900&max_price=27900')->assertOk()->assertSee('name="theme" value="beauty"',false)->assertSee('__JW_THEME_DEMO__',false)->assertSee('aria-label="Minimum price amount"',false);
  $this->get('/admin/website-design/cart-drawer?theme=beauty')->assertOk()->assertSee('data-beauty-cart',false);
  $this->assertSame('default',$service->resolve());
 }
 public function test_populated_cart_and_account_use_actual_items_and_snapshot_orders(): void
 {
  app(\App\Services\WebsiteDesignService::class)->save(['theme'=>'beauty','overrides'=>[]]);
  $p=Product::create(['title'=>'Actual saved item','handle'=>'saved-beauty','status'=>'active','base_price'=>4900]);
  $v=$p->variants()->create(['sku'=>'SAVED-BEAUTY','title'=>'Small','price'=>4900,'stock'=>0,'enabled'=>true]);
  $this->withSession(['cart'=>[$v->id=>2]])->get('/cart/drawer')->assertOk()->assertSee('Your bag (2)')->assertSee('Actual saved item')->assertSee('Small')->assertSee('RM 98.00')->assertSee('data-cart-drawer-form',false)->assertSee('Awaiting stock verification');
  $u=User::factory()->create(['is_admin'=>false]);$c=Customer::create(['user_id'=>$u->id,'email'=>$u->email,'first_name'=>'Buyer']);
  $c->wishlistItems()->create(['product_id'=>$p->id]);
  $order=\App\Models\Order::create(['number'=>'BEAUTY-ORDER','customer_id'=>$c->id,'email'=>$u->email,'status'=>'pending_payment','currency'=>'MYR','subtotal'=>4900,'total'=>4900]);
  $order->items()->create(['sku'=>'SNAPSHOT','title'=>'Purchased item','variant_title'=>'Small','quantity'=>1,'unit_price'=>4900,'line_total'=>4900]);
  $this->actingAs($u,'customer')->get('/account')->assertOk()->assertSee('Purchased item')->assertSee('BEAUTY-ORDER')->assertSee('Actual saved item')->assertSee('bs-overview-saved',false)->assertDontSee('submitPassword()',false);
 }
 public function test_known_inventory_allows_add_and_drawer_quantity_refresh(): void
 {
  app(\App\Services\WebsiteDesignService::class)->save(['theme'=>'beauty','overrides'=>[]]);
  $p=Product::create(['title'=>'Available item','handle'=>'available-beauty','status'=>'active','base_price'=>4900]);
  $v=$p->variants()->create(['sku'=>'AVAILABLE-BEAUTY','title'=>'Small','price'=>4900,'stock'=>0,'enabled'=>true]);
  config(['inventory.current_stock_only'=>true]);
  \Illuminate\Support\Facades\DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
  $poolKey=\Illuminate\Support\Facades\DB::table('inventory_configuration')->where('id',1)->value('operating_mode')==='shared_pool'?'shared':'store:website';
  \Illuminate\Support\Facades\DB::table('inventory_pools')->insertOrIgnore(['pool_key'=>$poolKey,'kind'=>$poolKey==='shared'?'shared':'store','created_at'=>now(),'updated_at'=>now()]);
  $pool=\Illuminate\Support\Facades\DB::table('inventory_pools')->where('pool_key',$poolKey)->value('id');
  \Illuminate\Support\Facades\DB::table('inventory_balances')->insert(['inventory_pool_id'=>$pool,'product_variant_id'=>$v->id,'current_stock'=>3,'version'=>0,'created_at'=>now(),'updated_at'=>now()]);
  $this->post('/cart/items',['variant_id'=>$v->id,'quantity'=>1])->assertRedirect()->assertSessionHasNoErrors();
  $this->patch('/cart/items/'.$v->id,['quantity'=>2])->assertRedirect()->assertSessionHasNoErrors();
  $this->get('/cart/drawer')->assertOk()->assertSee('Your bag (2)')->assertSee('RM 98.00')->assertSee('data-beauty-cart',false);
  $this->patch('/cart/items/'.$v->id,['quantity'=>4])->assertSessionHasErrors('quantity');
 }
 public function test_local_samples_are_idempotent_with_three_images_and_scoped_pdp_preview(): void
 {
  app()->instance('env','local');
  $this->seed(\Database\Seeders\BeautyThemeLocalSamplesSeeder::class);
  $this->seed(\Database\Seeders\BeautyThemeLocalSamplesSeeder::class);
  $category=\App\Models\Category::where('slug','beauty-design-sample')->firstOrFail();
  $this->assertSame(3,$category->products()->count());
  foreach($category->products as $product) {
   $this->assertSame(3,$product->images()->count());
   $this->assertSame(2,$product->pdpRelatedProducts()->count());
   $this->assertSame(0,$product->variants()->first()->stock);
  }
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get('/admin/website-design/preview?theme=beauty&surface=pdp&scope=category:'.$category->id)->assertOk()->assertSee('Tuscan Extra Virgin Olive Oil')->assertDontSee('Daily Serum')->assertSee('bs-gallery-rail',false)->assertSee('__JW_THEME_DEMO__',false);
  $this->get('/admin/website-design/preview?theme=beauty&scope=category:'.$category->id)->assertOk()->assertSee('Tuscan Extra Virgin Olive Oil')->assertDontSee('daily-essentials-hero.png')->assertSee('Buy now');
 }
 public function test_visual_fixture_seeder_rejects_production(): void
 {
  app()->instance('env','production');
  $this->expectException(\RuntimeException::class);
  (new \Database\Seeders\BeautyThemeLocalSamplesSeeder)->run();
 }
}
