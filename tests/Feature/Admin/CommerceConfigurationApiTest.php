<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Category;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_persist_coupon_shipping_and_disabled_gateway_metadata(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $coupon = $this->actingAs($admin)->postJson('/admin/api/coupons', [
            'code' => 'studio10', 'name' => 'Studio Welcome', 'description' => 'Ten percent off.', 'discountType' => 'percentage',
            'value' => 10, 'minimumSpend' => 50, 'usageLimit' => 100, 'perCustomerLimit' => 1, 'isActive' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'STUDIO10')->json('data');
        $this->assertDatabaseHas('coupons', ['id' => $coupon['id'], 'code' => 'STUDIO10', 'min_spend' => 5000]);

        $shipping = [['id'=>'rate-standard','name'=>'Standard Delivery','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],'flatRateCost'=>12.5,'freeShippingThreshold'=>200,'estimatedDays'=>'2-4 days','isActive'=>true,'createdAt'=>now()->toIso8601String(),'updatedAt'=>now()->toIso8601String()]];
        $payments = [['id'=>'paypal','name'=>'PayPal','category'=>'card','description'=>'Hosted PayPal checkout','logo'=>'','isEnabled'=>false,'isSandbox'=>true,'sortOrder'=>1,'credentials'=>[],'supportedCurrencies'=>['USD'],'transactionFee'=>['type'=>'fixed','feePayer'=>'merchant'],'limits'=>[],'features'=>[],'webhookUrl'=>'']];
        $this->actingAs($admin)->putJson('/admin/api/commerce-settings', ['shippingRates'=>$shipping,'paymentGateways'=>$payments,'globalPayment'=>['defaultGateway'=>'paypal']])->assertOk()
            ->assertJsonPath('data.shippingRates.0.flatRateCost', 12.5)->assertJsonPath('data.paymentGateways.0.credentialStatus', 'missing');
        $this->assertSame([], data_get(StoreSetting::query()->where('key', 'payment_gateways')->firstOrFail()->value, 'data.0.credentials'));
    }

    public function test_commerce_configuration_writes_require_admin_authentication(): void
    {
        $this->postJson('/admin/api/coupons', [])->assertUnauthorized();
        $this->putJson('/admin/api/commerce-settings', [])->assertUnauthorized();
        $this->putJson('/admin/api/commerce-settings/bank-transfer', [])->assertUnauthorized();
        $this->putJson('/admin/api/commerce-settings/cod', [])->assertUnauthorized();
    }

    public function test_enabled_online_gateway_requires_complete_environment_credentials(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/admin/api/commerce-settings', $this->commerceDocument([
            $this->gateway('stripe', true, []),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'paymentGateways.0.credentials.publishableKey',
                'paymentGateways.0.credentials.secretKey',
                'paymentGateways.0.credentials.webhookSecret',
            ]);
    }

    public function test_admin_can_save_online_gateway_credentials_without_receiving_them_back(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $credentials = [
            'publishableKey' => 'pk_test_public',
            'secretKey' => 'sk_test_private',
            'webhookSecret' => 'whsec_test_private',
        ];

        $this->actingAs($admin)->putJson('/admin/api/commerce-settings', $this->commerceDocument([
            $this->gateway('stripe', true, $credentials),
        ]))
            ->assertOk()
            ->assertJsonPath('data.paymentGateways.0.credentialStatus', 'configured')
            ->assertJsonMissing(['secretKey' => 'sk_test_private']);

        $this->assertSame($credentials, PaymentGatewayCredential::forGateway('stripe', 'sandbox')?->credentials);
    }

    public function test_admin_can_persist_bank_transfer_even_when_an_enabled_online_gateway_lacks_credentials(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => [
            $this->gateway('stripe', true, []),
        ]]]);

        $bankTransfer = [
            'isEnabled' => true,
            'allowProofUpload' => true,
            'bankAccounts' => [[
                'id' => 'maybank-primary', 'bankName' => 'Maybank', 'accountNumber' => '514000004432',
                'accountHolderName' => 'JW Studio', 'isPrimary' => true, 'isActive' => true,
            ]],
        ];

        $this->actingAs($admin)
            ->putJson('/admin/api/commerce-settings/bank-transfer', ['bankTransfer' => $bankTransfer])
            ->assertOk()
            ->assertJsonPath('data.bankTransfer.isEnabled', true);

        $this->assertSame($bankTransfer, data_get(StoreSetting::query()->where('key', 'bank_transfer')->sole()->value, 'data'));

        $this->withSession(['cart' => [$this->variant()->id => 1]])
            ->get('/checkout')
            ->assertOk()
            ->assertSee('Bank / DuitNow');
    }

    public function test_admin_can_persist_cod_even_when_an_enabled_online_gateway_lacks_credentials(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => [
            $this->gateway('paypal', true, []),
        ]]]);

        $cod = [
            'isEnabled' => true,
            'minOrderAmount' => 0,
            'maxOrderAmount' => 500,
            'allowedRegions' => 'all',
            'surchargeType' => 'fixed',
            'surchargeAmount' => 0,
        ];

        $this->actingAs($admin)
            ->putJson('/admin/api/commerce-settings/cod', ['codConfig' => $cod])
            ->assertOk()
            ->assertJsonPath('data.codConfig.isEnabled', true);

        $this->assertSame($cod, data_get(StoreSetting::query()->where('key', 'cod_config')->sole()->value, 'data'));

        $this->withSession(['cart' => [$this->variant()->id => 1]])
            ->get('/checkout')
            ->assertOk()
            ->assertSee('Cash on Delivery');
    }

    private function commerceDocument(array $gateways): array
    {
        return [
            'shippingRates' => [[
                'id' => 'standard',
                'name' => 'Standard Delivery',
                'zoneRegion' => 'all',
                'logisticType' => 'all',
                'calculationMode' => 'per_order',
                'rateType' => 'flat',
                'tiers' => [],
                'flatRateCost' => 0,
                'freeShippingThreshold' => 0,
                'isActive' => true,
            ]],
            'paymentGateways' => $gateways,
            'globalPayment' => ['defaultGateway' => 'stripe'],
        ];
    }

    private function gateway(string $id, bool $enabled, array $credentials): array
    {
        return [
            'id' => $id,
            'name' => ucfirst($id),
            'category' => 'card',
            'description' => 'Hosted gateway',
            'logo' => '',
            'isEnabled' => $enabled,
            'isSandbox' => true,
            'sortOrder' => 1,
            'credentials' => $credentials,
            'supportedCurrencies' => ['MYR'],
            'transactionFee' => ['type' => 'fixed', 'feePayer' => 'merchant'],
            'limits' => [],
            'features' => [],
            'webhookUrl' => 'https://merchant.test/payments/webhooks/'.$id,
        ];
    }

    private function variant()
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'admin-payment-audio', 'status' => 'active']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Admin Payment Headphones',
            'handle' => 'admin-payment-headphones',
            'brand' => 'JW Studio',
            'description' => 'Test',
            'base_price' => 10000,
            'status' => 'active',
        ]);

        return $product->variants()->create([
            'sku' => 'ADMIN-PAYMENT-1',
            'title' => 'Default',
            'price' => 10000,
            'stock' => 10,
            'enabled' => true,
        ]);
    }
}
