<?php

namespace Tests\Feature\Payment;

use App\Models\PaymentGatewayCredential;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\PaymentMethodAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_bank_transfer_with_an_active_account_is_checkout_eligible(): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'allowProofUpload' => true,
            'bankAccounts' => [[
                'id' => 'maybank-primary', 'bankName' => 'Maybank', 'bankCode' => 'MBBEMYKL',
                'accountHolderName' => 'JW Studio', 'accountNumber' => '514000004432',
                'isPrimary' => true, 'isActive' => true,
            ]],
        ]]]);

        $methods = app(PaymentMethodAvailabilityService::class)->checkoutMethods([
            'currency' => 'MYR', 'amount' => 38900, 'address' => ['country' => 'MY', 'state' => 'Selangor'],
        ]);

        $this->assertSame('Bank / DuitNow', $methods['bank_transfer']['label']);
        $this->assertSame('maybank-primary', $methods['bank_transfer']['bankTransfer']['bankAccounts'][0]['id']);
    }

    public function test_enabled_stripe_without_credentials_is_not_checkout_eligible_but_becomes_eligible_when_configured(): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => [[
            'id' => 'stripe', 'name' => 'Credit Card', 'isEnabled' => true, 'isSandbox' => true,
            'supportedCurrencies' => ['MYR'], 'limits' => [], 'sortOrder' => 1,
        ]]]]);

        $context = ['currency' => 'MYR', 'amount' => 38900, 'address' => ['country' => 'MY', 'state' => 'Selangor']];
        $this->assertArrayNotHasKey('stripe', app(PaymentMethodAvailabilityService::class)->checkoutMethods($context));

        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'stripe', 'environment' => 'sandbox',
            'credentials' => ['publishableKey' => 'pk_test_public', 'secretKey' => 'sk_test_private', 'webhookSecret' => 'whsec_test_private'],
        ]);

        $this->assertSame('Credit Card', app(PaymentMethodAvailabilityService::class)->checkoutMethods($context)['stripe']['label']);
    }

    public function test_cod_is_hidden_when_checkout_address_fails_its_region_rule(): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'cod_config'], ['value' => ['data' => [
            'isEnabled' => true, 'minOrderAmount' => 0, 'maxOrderAmount' => 0,
            'allowedRegions' => 'peninsular_only', 'surchargeType' => 'fixed', 'surchargeAmount' => 5,
        ]]]);

        $methods = app(PaymentMethodAvailabilityService::class)->checkoutMethods([
            'currency' => 'MYR', 'amount' => 38900, 'address' => ['country' => 'MY', 'state' => 'Sabah'],
        ]);

        $this->assertArrayNotHasKey('cod', $methods);
    }

    public function test_checkout_renders_only_the_operationally_eligible_payment_method(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => [[
            'id' => 'stripe', 'name' => 'Credit Card', 'isEnabled' => true, 'isSandbox' => true,
            'supportedCurrencies' => ['MYR'], 'limits' => [], 'sortOrder' => 1,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'bankAccounts' => [[
                'id' => 'maybank-primary', 'bankName' => 'Maybank', 'bankCode' => 'MBBEMYKL',
                'accountHolderName' => 'JW Studio', 'accountNumber' => '514000004432',
                'isPrimary' => true, 'isActive' => true,
            ]],
        ]]]);

        $this->withSession(['cart' => [$variant->id => 1]])->get('/checkout')
            ->assertOk()
            ->assertSee('Bank / DuitNow')
            ->assertDontSee('Credit Card');
    }

    private function variant()
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Studio Headphones', 'handle' => 'studio-headphones', 'brand' => 'JW Studio', 'description' => 'Test', 'base_price' => 10000, 'status' => 'active']);

        return $product->variants()->create(['sku' => 'STUDIO-1', 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]);
    }
}
