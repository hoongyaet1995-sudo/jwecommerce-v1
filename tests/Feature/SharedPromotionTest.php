<?php
namespace Tests\Feature;
use App\Models\{Product,User};
use App\Services\CartPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SharedPromotionTest extends TestCase
{
 use RefreshDatabase;
 public function test_legacy_zero_coupon_date_is_returned_as_empty():void {
  $coupon=\App\Models\Coupon::create(['code'=>'LEGACY','discount_type'=>'fixed_amount','value'=>2000,'is_active'=>true]);
  \Illuminate\Support\Facades\DB::table('coupons')->where('id',$coupon->id)->update(['ends_at'=>'0000-00-00 00:00:00']);
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson('/admin/api/coupons')->assertOk()->assertJsonPath('coupons.0.endsAt',null)->assertJsonPath('coupons.0.code','LEGACY');
 }

 public function test_admin_offer_persists_and_shared_pricing_uses_it():void {
  $p=Product::query()->create(['title'=>'Mix test','handle'=>'mix-test','base_price'=>4500,'status'=>'active','purchase_mode'=>'buy']);
  $v=$p->variants()->create(['sku'=>'MIX-TEST','title'=>'One','price'=>4500,'stock'=>100,'enabled'=>true]);
  $admin=User::factory()->create(['is_admin'=>true]);
  $r=$this->actingAs($admin)->postJson('/admin/api/promotion-offers',['cover_image'=>'/images/offer.png','image_settings'=>['mobile'=>'/images/offer-mobile.png','focus'=>'right'],'name'=>'Any three','description'=>'Three for 99','quantity'=>3,'price'=>9900,'repeat'=>true,'enabled'=>true,'product_ids'=>[$p->id]]);
  $r->assertCreated()->assertJsonPath('data.cover_image','/images/offer.png')->assertJsonPath('data.image_settings.mobile','/images/offer-mobile.png');
  $this->assertDatabaseHas('promotion_offers',['name'=>'Any three','price'=>9900]);
  $summary=app(CartPricingService::class)->summarize(collect([['variant'=>$v,'quantity'=>7]]));
  $this->assertSame(7200,$summary['discount']);
  $this->assertSame(7200,array_sum($summary['promotion_snapshot']['allocations']));
  $this->getJson('/admin/api/promotion-offers/products?search=MIX-TEST')->assertOk()->assertJsonPath('total',1);
 }
 public function test_non_admin_cannot_create_offer():void {
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->postJson('/admin/api/promotion-offers',[])->assertUnauthorized();
 }
}
