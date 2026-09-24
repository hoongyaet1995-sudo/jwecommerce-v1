<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCommerceRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_checkout_defers_reservation_and_unpaid_cancel_does_not_restore_legacy(): void
    {
        $variant=$this->variant();
        $this->configureStandardShipping();$this->configureBankTransfer();
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        \Illuminate\Support\Facades\DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        \Illuminate\Support\Facades\DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'safety_stock'=>2,'counted_at'=>now()]);
        $activation=app(\App\Services\Inventory\ManagedInventoryActivation::class)->activate('website',$balance['balance_id'],\App\Models\User::factory()->create(['is_admin'=>true]),'New orders');
        $this->travel(2)->seconds();
        $variant->update(['stock'=>0]);
        request()->setLaravelSession(app('session.store'));
        $quickAdd=\Illuminate\Support\Facades\Blade::render('<x-storefront.quick-add-variant-modal :product="$product" :variants="$variants" :images="$images" />',[
            'product'=>$variant->product,'variants'=>collect([$variant->fresh()]),'images'=>collect(),
        ]);
        $this->assertStringContainsString('"stock":18',$quickAdd);
        $this->get(route('storefront.products.show',['handle'=>$variant->product->handle]))->assertOk()->assertSee('In Stock (18 units)',false);
        $this->post('/cart/items',['variant_id'=>$variant->id,'quantity'=>2])->assertRedirect('/cart')->assertSessionHasNoErrors();
        $this->get('/cart')->assertOk()->assertSee('name="quantity" value="3"',false);
        $this->get('/cart/drawer')->assertOk()->assertSee('name="quantity" value="3"',false);
        $this->withSession(['cart'=>[$variant->id=>2]])->post('/checkout',$this->checkoutData('bank_transfer'))->assertRedirect('/order/success');
        $order=\App\Models\Order::query()->sole();
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'inventory_order_activation_id'=>$activation['id']]);
        $this->assertDatabaseCount('stock_allocations',0);
        $this->assertEquals(0,$variant->fresh()->stock);
        app(\App\Services\CheckoutService::class)->transition($order,'cancelled');
        $this->assertEquals(0,$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->withSession(['cart'=>[$variant->id=>2]])->post('/checkout',$this->checkoutData('bank_transfer'))->assertRedirect('/order/success');
        $paid=\App\Models\Order::query()->latest('id')->first();
        $checkout=app(\App\Services\CheckoutService::class);
        $checkout->transition($paid,'paid');$checkout->transition($paid,'paid');
        $this->assertDatabaseCount('stock_allocations',1);
        $this->assertSame(16,app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant->fresh()));
        $checkout->transition($paid,'cancelled');
        $this->assertSame(18,app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant->fresh()));
        $this->assertEquals(0,$variant->fresh()->stock);
        $this->withSession(['cart'=>[$variant->id=>2]])->patch('/cart/items/'.$variant->id,['quantity'=>19])->assertSessionHasErrors('quantity');
        $this->assertSame(2,session('cart.'.$variant->id));
    }

    public function test_checkout_cannot_sell_units_held_by_a_marketplace_allocation(): void
    {
        $variant = $this->variant();
        $this->configureStandardShipping();
        $this->configureBankTransfer();
        $service = app(\App\Services\Inventory\StockAllocationService::class);
        $allocation = $service->reserve('marketplace_order', 99, 'line-1', $variant->id, 9);

        $this->withSession(['cart'=>[$variant->id=>2]])
            ->post('/checkout', $this->checkoutData('bank_transfer'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(1, $variant->fresh()->stock);

        $service->release($allocation);
        $this->withSession(['cart'=>[$variant->id=>2]])
            ->post('/checkout', $this->checkoutData('bank_transfer'))
            ->assertRedirect('/order/success');
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(8, $variant->fresh()->stock);
    }

    public function test_self_operated_cancellation_does_not_release_marketplace_reserved_units(): void
    {
        $variant = $this->variant();
        $this->configureStandardShipping();
        $this->configureBankTransfer();
        $allocation = app(\App\Services\Inventory\StockAllocationService::class)
            ->reserve('marketplace_order', 99, 'line-1', $variant->id, 3);
        $this->withSession(['cart'=>[$variant->id=>2]])
            ->post('/checkout', $this->checkoutData('bank_transfer'))
            ->assertRedirect('/order/success');
        $this->assertEquals(5, $variant->fresh()->stock);
        app(\App\Services\CheckoutService::class)->transition(\App\Models\Order::query()->sole(), 'cancelled');
        $this->assertEquals(7, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_allocations', ['id'=>$allocation, 'status'=>'reserved']);
        app(\App\Services\CheckoutService::class)->transition(\App\Models\Order::query()->sole(), 'cancelled');
        $this->assertDatabaseCount('inventory_movements', 3);
        $this->assertEquals(3, $variant->fresh()->stock_version);
    }

    public function test_checkout_snapshots_coupon_usage_shipping_rate_and_simulated_payment(): void
    {
        $variant = $this->variant();
        $coupon = Coupon::query()->create(['code'=>'ONEUSE','name'=>'One use','discount_type'=>'percentage','value'=>10,'usage_limit'=>1,'usage_count'=>0,'per_customer_limit'=>1,'is_active'=>true]);
        StoreSetting::query()->updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[['id'=>'standard','name'=>'Standard Delivery','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>12.5,'freeShippingThreshold'=>200,'isActive'=>true]]]]);
        $this->configureBankTransfer();

        $response = $this->withSession(['cart'=>[$variant->id=>1], 'coupon_code'=>'ONEUSE'])->post('/checkout', $this->checkoutData('bank_transfer'));
        $response->assertRedirect('/order/success');
        $this->assertDatabaseHas('orders', ['coupon_id'=>$coupon->id,'coupon_code'=>'ONEUSE','subtotal'=>10000,'discount_amount'=>1000,'shipping_amount'=>1250,'total'=>10250]);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id'=>$coupon->id,'customer_email'=>'buyer@example.com','discount_amount'=>1000]);
        $this->assertDatabaseHas('coupons', ['id'=>$coupon->id,'usage_count'=>1]);
        $this->assertDatabaseHas('order_payments', ['gateway'=>'bank_transfer','status'=>'pending']);

        $this->flushSession();
        $this->post('/cart/coupon', ['code'=>'ONEUSE'])->assertSessionHas('coupon_error');
    }

    public function test_checkout_coupon_application_returns_to_checkout_and_renders_the_drawer_coupon_controls(): void
    {
        $variant = $this->variant();
        Coupon::query()->create([
            'code' => 'CHECKOUT10',
            'name' => 'Checkout ten percent',
            'discount_type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $response = $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/cart/coupon', ['code' => 'checkout10', 'return_to' => 'checkout']);

        $response->assertRedirect('/checkout');
        $response->assertSessionHas('coupon_code', 'CHECKOUT10');

        $this->withSession(['cart' => [$variant->id => 1], 'coupon_code' => 'CHECKOUT10'])
            ->get('/checkout')
            ->assertOk()
            ->assertSee('data-checkout-coupon-form', false)
            ->assertSee('Coupon (e.g. WELCOME10)')
            ->assertSee('CHECKOUT10')
            ->assertSee('Remove');
    }

    public function test_checkout_coupon_application_rejects_a_coupon_below_its_minimum_spend(): void
    {
        $variant = $this->variant();
        Coupon::query()->create([
            'code' => 'MIN500',
            'name' => 'Minimum spend coupon',
            'discount_type' => 'percentage',
            'value' => 10,
            'min_spend' => 50000,
            'is_active' => true,
        ]);

        $response = $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/cart/coupon', ['code' => 'MIN500', 'return_to' => 'checkout']);

        $response->assertRedirect('/checkout');
        $response->assertSessionMissing('coupon_code');
        $response->assertSessionHas('coupon_error', 'Coupon requires a minimum spend of RM 500.00.');
    }

    public function test_checkout_coupon_removal_returns_to_checkout(): void
    {
        $response = $this->withSession(['coupon_code' => 'CHECKOUT10'])
            ->delete('/cart/coupon', ['return_to' => 'checkout']);

        $response->assertRedirect('/checkout');
        $response->assertSessionMissing('coupon_code');
    }

    public function test_checkout_rejects_a_disabled_configured_payment_method(): void
    {
        $variant = $this->variant();
        $this->configureStandardShipping();
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
        $this->configureBankTransfer();
        $data = $this->checkoutData('bank_transfer'); $data['state'] = 'Sabah'; $data['delivery'] = 'sabah';
        $this->withSession(['cart'=>[$variant->id=>1]])->post('/checkout', $data)->assertRedirect('/order/success');
        $this->assertDatabaseHas('orders', ['shipping_method' => 'sabah', 'shipping_label' => 'Standard Sabah', 'shipping_amount'=>2000,'total'=>12000]);
        $order = \App\Models\Order::query()->sole();
        $this->assertSame('sabah', $order->shipping_snapshot['id']);
        $this->assertSame('sabah', $order->shipping_snapshot['zone']);
        StoreSetting::query()->updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[
            ['id'=>'sabah','name'=>'Renamed Sabah Rate','zoneRegion'=>'sabah','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>99,'isActive'=>true],
        ]]]);
        $order->refresh();
        $this->assertSame('Standard Sabah', $order->shipping_label);
        $this->assertSame(2000, $order->shipping_amount);
        $this->assertSame(20, $order->shipping_snapshot['flat_rate_cost']);
    }

    public function test_checkout_quote_shows_only_exact_active_rates_eligible_for_the_address(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[
            ['id'=>'peninsular-standard','name'=>'Peninsular Standard','zoneRegion'=>'peninsular','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>8,'isActive'=>true],
            ['id'=>'sabah-express','name'=>'Sabah Express','zoneRegion'=>'sabah','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>20,'isActive'=>true],
            ['id'=>'disabled-global','name'=>'Disabled Global','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>1,'isActive'=>false],
        ]]]);

        $this->withSession(['cart'=>[$variant->id=>1]])
            ->postJson('/checkout/shipping-quote', ['country' => 'MY', 'state' => 'Sabah', 'postal_code' => '88000'])
            ->assertOk()
            ->assertJsonPath('data.options.0.id', 'sabah-express')
            ->assertJsonPath('data.options.0.shipping', 2000)
            ->assertJsonCount(1, 'data.options');
    }

    public function test_checkout_quote_prices_each_matching_rate_by_its_exact_rate_id(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [
            ['id' => 'general-regional', 'name' => 'General Regional Shipping Rate', 'zoneRegion' => 'peninsular', 'logisticType' => 'all', 'calculationMode' => 'weight', 'rateType' => 'custom', 'tiers' => [['min' => 0, 'max' => 2, 'cost' => 8]], 'flatRateCost' => 8, 'freeShippingThreshold' => 500, 'isActive' => true],
            ['id' => 'standard-global', 'name' => 'Standard Delivery', 'zoneRegion' => 'all', 'logisticType' => 'all', 'calculationMode' => 'per_order', 'rateType' => 'flat', 'tiers' => [], 'flatRateCost' => 0, 'freeShippingThreshold' => 150, 'isActive' => true],
        ]]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->postJson('/checkout/shipping-quote', ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000'])
            ->assertOk()
            ->assertJsonPath('data.options.0.id', 'general-regional')
            ->assertJsonPath('data.options.0.shipping', 800)
            ->assertJsonPath('data.options.1.id', 'standard-global')
            ->assertJsonPath('data.options.1.shipping', 0);
    }

    public function test_checkout_does_not_claim_free_shipping_before_an_address_matches_an_active_rate(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'peninsular-weight', 'name' => 'Peninsular Weight Delivery', 'zoneRegion' => 'peninsular',
            'logisticType' => 'all', 'calculationMode' => 'weight', 'rateType' => 'custom',
            'tiers' => [['id' => 'first-tier', 'min' => 0, 'max' => 2, 'cost' => 8]],
            'flatRateCost' => 8, 'freeShippingThreshold' => 500, 'isActive' => true,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rate_cents'], ['value' => ['data' => 1200]]);
        StoreSetting::query()->updateOrCreate(['key' => 'free_shipping_threshold_cents'], ['value' => ['data' => 15000]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->get('/checkout')
            ->assertOk()
            ->assertSee('Shipping calculated at checkout');
    }

    public function test_cart_does_not_promote_legacy_free_shipping_when_only_an_address_specific_rate_is_active(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'peninsular-weight', 'name' => 'Peninsular Weight Delivery', 'zoneRegion' => 'peninsular',
            'logisticType' => 'all', 'calculationMode' => 'weight', 'rateType' => 'custom',
            'tiers' => [['id' => 'first-tier', 'min' => 0, 'max' => 2, 'cost' => 8]],
            'flatRateCost' => 8, 'freeShippingThreshold' => 500, 'isActive' => true,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rate_cents'], ['value' => ['data' => 1200]]);
        StoreSetting::query()->updateOrCreate(['key' => 'free_shipping_threshold_cents'], ['value' => ['data' => 15000]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->get('/cart')
            ->assertOk()
            ->assertSee('Add')
            ->assertSee('more for Free Shipping')
            ->assertSee('eligible delivery regions');
    }

    public function test_checkout_marks_a_prefilled_address_for_an_initial_shipping_quote(): void
    {
        $variant = $this->variant();
        $this->configureStandardShipping();

        $this->withSession(['cart' => [$variant->id => 1], '_old_input' => [
            'country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000',
        ]])
            ->get('/checkout')
            ->assertOk()
            ->assertSee('data-checkout-initial-shipping-quote="true"', false);
    }

    public function test_cart_uses_a_shipping_neutral_unlocked_message_for_an_actual_free_rate(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'standard-global', 'name' => 'Standard Delivery', 'zoneRegion' => 'all',
            'logisticType' => 'all', 'calculationMode' => 'per_order', 'rateType' => 'flat',
            'tiers' => [], 'flatRateCost' => 8, 'freeShippingThreshold' => 50, 'isActive' => true,
        ]]]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->get('/cart')
            ->assertOk()
            ->assertSee('You unlocked complimentary shipping!')
            ->assertDontSee('Complimentary Express Shipping');
    }

    public function test_checkout_rejects_shipping_rate_that_does_not_serve_the_submitted_address(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[
            ['id'=>'peninsular-standard','name'=>'Peninsular Standard','zoneRegion'=>'peninsular','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>8,'isActive'=>true],
            ['id'=>'sabah-express','name'=>'Sabah Express','zoneRegion'=>'sabah','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>20,'isActive'=>true],
        ]]]);
        $this->configureBankTransfer();
        $data = $this->checkoutData('bank_transfer');
        $data['state'] = 'Sabah';
        $data['delivery'] = 'peninsular-standard';

        $this->withSession(['cart'=>[$variant->id=>1]])->post('/checkout', $data)
            ->assertSessionHasErrors('delivery');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_only_renders_database_enabled_payment_and_delivery_methods(): void
    {
        $variant = $this->variant();
        $this->configureStandardShipping();
        $this->configureGateway('stripe');
        $this->configureGateway('paypal');
        $this->withSession(['cart'=>[$variant->id=>1]])->get('/checkout')->assertOk()
            ->assertSee('Standard Delivery')->assertDontSee('White-Glove VIP Same Day Courier')
            ->assertSee('Credit Card')->assertSee('PayPal')->assertDontSee('Crypto (USDC)');
    }

    public function test_checkout_snapshots_the_shoppers_selected_currency_and_fx_rate(): void
    {
        $variant = $this->variant();
        $this->configureStandardShipping();
        $this->configureBankTransfer();
        StoreSetting::query()->updateOrCreate(['key' => 'currencies'], ['value' => ['data' => [
            ['id' => 'MYR', 'code' => 'MYR', 'title' => 'Malaysian Ringgit', 'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1, 'isDefault' => true, 'isEnabled' => true],
            ['id' => 'USD', 'code' => 'USD', 'title' => 'US Dollar', 'symbolLeft' => '$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2245, 'isDefault' => false, 'isEnabled' => true],
        ]]]);

        $this->withSession(['cart' => [$variant->id => 1], 'storefront_currency' => 'USD'])
            ->post('/checkout', $this->checkoutData('bank_transfer'))
            ->assertRedirect('/order/success');

        $this->assertDatabaseHas('orders', [
            'currency' => 'USD',
            'currency_exchange_rate' => 0.2245,
        ]);
    }

    public function test_guest_checkout_reuses_an_existing_customer_with_the_same_normalized_email(): void
    {
        $variant = $this->variant();
        $customer = Customer::query()->create(['email' => 'buyer@example.com']);
        $this->configureStandardShipping();
        $this->configureBankTransfer();
        $data = $this->checkoutData('bank_transfer');
        $data['email'] = 'BUYER@example.test';
        $customer->update(['email' => 'buyer@example.test']);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $data)
            ->assertRedirect('/order/success');

        self::assertSame(1, Customer::query()->whereRaw('lower(email) = ?', ['buyer@example.test'])->count());
        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'email' => 'BUYER@example.test']);
    }

    private function checkoutData(string $paymentMethod = 'paypal'): array
    {
        return ['email'=>'buyer@example.com','first_name'=>'Alex','last_name'=>'Buyer','line_1'=>'10 Studio Road','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY','delivery'=>'standard','payment_method'=>$paymentMethod];
    }

    private function variant()
    {
        $category = Category::query()->create(['name'=>'Audio','slug'=>'audio','status'=>'active']);
        $product = Product::query()->create(['category_id'=>$category->id,'title'=>'Studio Headphones','handle'=>'studio-headphones','brand'=>'JW Studio','description'=>'Test','base_price'=>10000,'status'=>'active']);
        return $product->variants()->create(['sku'=>'STUDIO-1','title'=>'Default','price'=>10000,'stock'=>10,'enabled'=>true]);
    }

    private function configureGateway(string $gateway): void
    {
        $credentials = match ($gateway) {
            'stripe' => ['publishableKey' => 'pk_test_public', 'secretKey' => 'sk_test_private', 'webhookSecret' => 'whsec_test_private'],
            'paypal' => ['clientId' => 'paypal_test_client', 'clientSecret' => 'paypal_test_secret'],
        };

        $configured = data_get(StoreSetting::query()->where('key', 'payment_gateways')->value('value'), 'data', []);
        $configured = collect(is_array($configured) ? $configured : [])
            ->reject(fn (array $item): bool => ($item['id'] ?? null) === $gateway)
            ->values()
            ->all();
        $configured[] = [
            'id' => $gateway,
            'name' => $gateway === 'stripe' ? 'Credit Card' : 'PayPal',
            'isEnabled' => true,
            'isSandbox' => true,
            'supportedCurrencies' => ['MYR', 'USD'],
            'limits' => [],
        ];
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => $configured]]);
        PaymentGatewayCredential::query()->updateOrCreate(
            ['gateway_id' => $gateway, 'environment' => 'sandbox'],
            ['credentials' => $credentials],
        );
    }

    private function configureBankTransfer(): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'bankAccounts' => [[
                'id' => 'maybank-primary', 'bankName' => 'Maybank', 'accountNumber' => '514000004432',
                'isPrimary' => true, 'isActive' => true,
            ]],
        ]]]);
    }

    private function configureStandardShipping(): void
    {
        StoreSetting::query()->updateOrCreate(['key'=>'shipping_rates'],['value'=>['data'=>[[
            'id'=>'standard','name'=>'Standard Delivery','zoneRegion'=>'all','logisticType'=>'all',
            'calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>0,
            'freeShippingThreshold'=>0,'isActive'=>true,
        ]]]]);
    }
}
