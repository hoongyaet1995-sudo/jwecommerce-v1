<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCommerceRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_snapshots_coupon_usage_shipping_rate_and_simulated_payment(): void
    {
        $variant = $this->variant();
        $coupon = Coupon::query()->create(['code'=>'ONEUSE','name'=>'One use','discount_type'=>'percentage','value'=>10,'usage_limit'=>1,'usage_count'=>0,'per_customer_limit'=>1,'is_active'=>true]);
        StoreSetting::query()->updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[['id'=>'standard','name'=>'Standard Delivery','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>12.5,'freeShippingThreshold'=>200,'isActive'=>true]]]]);
        StoreSetting::query()->updateOrCreate(['key'=>'payment_gateways'],['value'=>['data'=>[['id'=>'paypal','isEnabled'=>true]]]]);

        $response = $this->withSession(['cart'=>[$variant->id=>1], 'coupon_code'=>'ONEUSE'])->post('/checkout', $this->checkoutData());
        $response->assertRedirect('/order/success');
        $this->assertDatabaseHas('orders', ['coupon_id'=>$coupon->id,'coupon_code'=>'ONEUSE','subtotal'=>10000,'discount_amount'=>1000,'shipping_amount'=>1250,'total'=>10250]);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id'=>$coupon->id,'customer_email'=>'buyer@example.com','discount_amount'=>1000]);
        $this->assertDatabaseHas('coupons', ['id'=>$coupon->id,'usage_count'=>1]);
        $this->assertDatabaseHas('order_payments', ['gateway'=>'paypal','status'=>'pending']);

        $this->flushSession();
        $this->post('/cart/coupon', ['code'=>'ONEUSE'])->assertSessionHas('coupon_error');
    }

    public function test_checkout_rejects_a_disabled_configured_payment_method(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key'=>'payment_gateways'],['value'=>['data'=>[['id'=>'paypal','isEnabled'=>false]]]]);
        $this->withSession(['cart'=>[$variant->id=>1]])->post('/checkout', $this->checkoutData())
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_selects_the_matching_shipping_zone(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[
            ['id'=>'sabah','name'=>'Standard Sabah','zoneRegion'=>'sabah','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>20,'isActive'=>true],
            ['id'=>'peninsular','name'=>'Standard Peninsular','zoneRegion'=>'peninsular','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>8,'isActive'=>true],
        ]]]);
        $data = $this->checkoutData(); $data['state'] = 'Sabah'; $data['payment_method'] = 'simulated_card';
        $this->withSession(['cart'=>[$variant->id=>1]])->post('/checkout', $data)->assertRedirect('/order/success');
        $this->assertDatabaseHas('orders', ['shipping_amount'=>2000,'total'=>12000]);
    }

    public function test_checkout_only_renders_database_enabled_payment_and_delivery_methods(): void
    {
        $variant = $this->variant();
        $this->withSession(['cart'=>[$variant->id=>1]])->get('/checkout')->assertOk()
            ->assertSee('Standard Delivery')->assertDontSee('White-Glove VIP Same Day Courier')
            ->assertSee('Credit Card')->assertSee('PayPal')->assertDontSee('Crypto (USDC)');
    }

    private function checkoutData(): array
    {
        return ['email'=>'buyer@example.com','first_name'=>'Alex','last_name'=>'Buyer','line_1'=>'10 Studio Road','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY','delivery'=>'standard','payment_method'=>'paypal'];
    }

    private function variant()
    {
        $category = Category::query()->create(['name'=>'Audio','slug'=>'audio','status'=>'active']);
        $product = Product::query()->create(['category_id'=>$category->id,'title'=>'Studio Headphones','handle'=>'studio-headphones','brand'=>'JW Studio','description'=>'Test','base_price'=>10000,'status'=>'active']);
        return $product->variants()->create(['sku'=>'STUDIO-1','title'=>'Default','price'=>10000,'stock'=>10,'enabled'=>true]);
    }
}
