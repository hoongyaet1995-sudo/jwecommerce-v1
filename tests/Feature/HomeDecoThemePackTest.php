<?php
namespace Tests\Feature;
use App\Models\{StoreSetting,User,Customer,Product};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Password,Hash};
use Tests\TestCase;
class HomeDecoThemePackTest extends TestCase
{
 use RefreshDatabase;
 private function theme(): void { StoreSetting::updateOrCreate(['key'=>'website_design'],['value'=>['data'=>['theme'=>'home_deco','overrides'=>[]]]]); }
 public function test_theme_switch_selects_real_auth_pdp_and_drawer_renderers(): void
 {
  $this->theme();
  foreach(['/account/login','/account/register','/account/forgot-password'] as $url) $this->get($url)->assertOk()->assertSee('data-home-deco-auth',false);
  $p=Product::create(['title'=>'Table','handle'=>'theme-table','status'=>'active','base_price'=>4900]);
  $p->variants()->create(['sku'=>'THEME-TABLE','title'=>'Natural','price'=>4900,'stock'=>0,'enabled'=>true]);
  $this->get('/products/theme-table')->assertOk()->assertSee('data-home-deco-pdp',false);
  $this->get('/cart/drawer')->assertOk()->assertSee('data-home-deco-cart',false);
  StoreSetting::where('key','website_design')->update(['value'=>['data'=>['theme'=>'default','overrides'=>[]]]]);
  $this->get('/account/login')->assertOk()->assertDontSee('data-home-deco-auth',false);
 }
 public function test_account_uses_customer_guard_and_theme_without_sample_customer_data(): void
 {
  $this->theme();$u=User::factory()->create(['is_admin'=>false]);Customer::create(['user_id'=>$u->id,'email'=>$u->email,'first_name'=>'Buyer']);
  $this->actingAs($u,'customer')->get('/account')->assertOk()->assertSee('data-home-deco-account',false)->assertDontSee('1250 PTS')->assertDontSee('alex@example.com');
 }
 public function test_customer_can_reset_with_valid_token_and_admin_cannot_use_customer_reset(): void
 {
  $this->theme();$u=User::factory()->create(['is_admin'=>false]);Customer::create(['user_id'=>$u->id,'email'=>$u->email,'first_name'=>'Buyer']);
  $token=Password::createToken($u);
  $this->get('/account/reset-password/'.$token.'?email='.urlencode($u->email))->assertOk();
  $this->post('/account/reset-password',['token'=>$token,'email'=>$u->email,'password'=>'New-test-pass-123','password_confirmation'=>'New-test-pass-123'])->assertRedirect('/account/login');
  $this->assertTrue(Hash::check('New-test-pass-123',$u->fresh()->password));
  $admin=User::factory()->create(['is_admin'=>true]);$token=Password::createToken($admin);
  $this->post('/account/reset-password',['token'=>$token,'email'=>$admin->email,'password'=>'Changed-test-123','password_confirmation'=>'Changed-test-123'])->assertSessionHasErrors('email');
  $this->assertFalse(Hash::check('Changed-test-123',$admin->fresh()->password));
 }
 public function test_admin_surface_previews_are_non_sensitive_and_do_not_change_saved_theme(): void
 {
  $u=User::factory()->create(['is_admin'=>true]);$this->actingAs($u);
  foreach(['login','register','forgot','account'] as $surface) $this->get('/admin/website-design/preview?theme=home_deco&surface='.$surface)->assertOk()->assertSee('disabled',false);
  $this->get('/admin/website-design/preview?theme=home_deco&surface=account')->assertSee('sample@example.com')->assertDontSee($u->email);
  $this->assertSame('default',app(\App\Services\WebsiteDesignService::class)->resolve());
 }
 public function test_account_saved_products_use_product_identity_and_orders_keep_snapshot_totals(): void
 {
  $this->theme();$u=User::factory()->create(['is_admin'=>false]);$c=Customer::create(['user_id'=>$u->id,'email'=>$u->email,'first_name'=>'Buyer']);
  $p=Product::create(['title'=>'Saved chair','handle'=>'saved-theme-chair','status'=>'active','base_price'=>19900]);$c->wishlistItems()->create(['product_id'=>$p->id]);
  $order=\App\Models\Order::create(['number'=>'THEME-ORDER','customer_id'=>$c->id,'email'=>$u->email,'status'=>'pending_payment','currency'=>'MYR','subtotal'=>4900,'total'=>4900]);
  $order->items()->create(['sku'=>'SNAPSHOT','title'=>'Purchased table','variant_title'=>'Natural','quantity'=>1,'unit_price'=>4900,'line_total'=>4900]);
  \App\Models\OrderPayment::create(['order_id'=>$order->id,'gateway'=>'bank_transfer','provider'=>'bank_transfer','environment'=>'offline','status'=>'pending','reference'=>'THEME-BANK','idempotency_key'=>'theme-bank','amount'=>4900,'currency'=>'MYR','proof_status'=>'none']);
  $this->actingAs($u,'customer')->get('/account')->assertOk()->assertSee('Saved chair')->assertSee('Purchased table')->assertSee('THEME-ORDER')->assertSee('data-bank-payment-proof',false)->assertDontSee('US-EIN-884920194')->assertDontSee('Official Commercial Tax Invoice');
 }
 public function test_reset_notification_uses_customer_route_and_invalid_token_does_not_change_password(): void
 {
  \Illuminate\Support\Facades\Notification::fake();$u=User::factory()->create(['is_admin'=>false]);Customer::create(['user_id'=>$u->id,'email'=>$u->email]);
  $this->post('/account/forgot-password',['email'=>$u->email])->assertRedirect()->assertSessionHas('status');
  \Illuminate\Support\Facades\Notification::assertSentTo($u,\Illuminate\Auth\Notifications\ResetPassword::class,function($n)use($u){return str_contains($n->toMail($u)->actionUrl,'/account/reset-password/');});
  $before=$u->password;
  $this->post('/account/reset-password',['email'=>$u->email,'token'=>'invalid','password'=>'Never-saved-123','password_confirmation'=>'Never-saved-123'])->assertSessionHasErrors('email');
  $this->assertSame($before,$u->fresh()->password);
 }

 public function test_address_edit_is_owned_by_customer_and_preserves_default_uniqueness(): void
 {
  $u=User::factory()->create(['is_admin'=>false]);$c=Customer::create(['user_id'=>$u->id,'email'=>$u->email]);
  $address=$c->addresses()->create(['first_name'=>'Buyer','last_name'=>'One','line_1'=>'Old street','city'=>'KL','postal_code'=>'50000','country'=>'MY','is_default'=>true]);
  $data=['first_name'=>'Buyer','last_name'=>'One','line_1'=>'Updated street','city'=>'KL','postal_code'=>'50000','country'=>'MY','is_default'=>1];
  $this->actingAs($u,'customer')->patch('/account/addresses/'.$address->id,$data)->assertRedirect('/account');
  $this->assertSame('Updated street',$address->fresh()->line_1);
  $other=User::factory()->create(['is_admin'=>false]);Customer::create(['user_id'=>$other->id,'email'=>$other->email]);
  $this->actingAs($other,'customer')->patch('/account/addresses/'.$address->id,$data)->assertNotFound();
 }

 public function test_admin_preview_drawer_keeps_unsaved_theme_and_is_protected(): void
 {
  $this->get('/admin/website-design/cart-drawer?theme=home_deco')->assertRedirect();
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get('/admin/website-design/cart-drawer?theme=home_deco')->assertOk()->assertSee('data-home-deco-cart',false);
  $this->get('/admin/website-design/cart-drawer?theme=default')->assertOk()->assertDontSee('data-home-deco-cart',false);
  $this->get('/admin/website-design/cart-drawer?theme=unknown')->assertUnprocessable();
  $this->assertSame('default',app(\App\Services\WebsiteDesignService::class)->resolve());
 }

}
