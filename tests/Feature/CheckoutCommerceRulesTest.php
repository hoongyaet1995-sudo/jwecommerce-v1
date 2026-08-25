<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\PaymentGatewayCredential;
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
        $this->configureBankTransfer();
        $data = $this->checkoutData('bank_transfer'); $data['state'] = 'Sabah';
        $this->withSession(['cart'=>[$variant->id=>1]])->post('/checkout', $data)->assertRedirect('/order/success');
        $this->assertDatabaseHas('orders', ['shipping_amount'=>2000,'total'=>12000]);
    }

    public function test_checkout_only_renders_database_enabled_payment_and_delivery_methods(): void
    {
        $variant = $this->variant();
        $this->configureGateway('stripe');
        $this->configureGateway('paypal');
        $this->withSession(['cart'=>[$variant->id=>1]])->get('/checkout')->assertOk()
            ->assertSee('Standard Delivery')->assertDontSee('White-Glove VIP Same Day Courier')
            ->assertSee('Credit Card')->assertSee('PayPal')->assertDontSee('Crypto (USDC)');
    }

    public function test_checkout_snapshots_the_shoppers_selected_currency_and_fx_rate(): void
    {
        $variant = $this->variant();
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
}
