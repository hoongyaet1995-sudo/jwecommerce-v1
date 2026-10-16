<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Product,Order};
class WeddingCommerceRenderTest extends TestCase
{
 use RefreshDatabase;
 public function test_commerce_scenes_render_shared_forms_without_business_writes():void
 {
  $this->assertSame('sqlite',config('database.default'));$this->assertSame(':memory:',config('database.connections.sqlite.database'));
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  foreach(['shopping-cart','cart-drawer','checkout','quotation-checkout','checkout-success','payment-pending','order-tracking','order-tracking-verify-code','order-tracking-verified'] as $scene){
   $response=$this->get('/admin/website-design/acceptance/sample/'.$scene.'?theme=wedding')->assertOk()->assertHeader('X-Theme-Data','dummy');
   if($scene==='checkout')$response->assertSee('data-checkout-shipping-quote-url',false)->assertSee('name="first_name"',false)->assertSee('name="payment_method"',false);
   if($scene==='quotation-checkout')$response->assertSee('data-quotation-checkout',false)->assertSee('name="payment_method"',false);
   if($scene==='payment-pending')$response->assertSee('One more step')->assertSee('DEMO-ONLY BANK')->assertDontSee('Your order has been confirmed.');
   if($scene==='checkout-success')$response->assertSee('Your order has been confirmed.')->assertSee('Paid');
   if($scene==='order-tracking-verify-code')$response->assertSee('name="code"',false)->assertDontSee('name="order_number"',false);
  }
  $this->assertSame(0,Product::count());$this->assertSame(0,Order::count());
 }
}
