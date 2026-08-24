<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StorefrontCurrencyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_enabled_session_currency_and_converts_base_cents_for_display(): void
    {
        $this->currencySettings();
        $this->setStorefrontCurrency('USD');

        $context = app(\App\Support\StorefrontCurrencyContext::class);

        $this->assertSame('USD', $context->current()['code']);
        $this->assertSame('$44.68', $context->format(19900));
    }

    public function test_it_falls_back_to_the_store_default_when_the_session_currency_is_disabled(): void
    {
        $this->currencySettings();
        $this->setStorefrontCurrency('GBP');

        $context = app(\App\Support\StorefrontCurrencyContext::class);

        $this->assertSame('MYR', $context->current()['code']);
        $this->assertSame('RM 199.00', $context->format(19900));
    }

    public function test_the_storefront_money_blade_directive_uses_the_same_session_context(): void
    {
        $this->currencySettings();
        $this->setStorefrontCurrency('USD');

        $this->assertSame('$44.68', trim(Blade::render('@storefrontMoney(19900)')));
    }

    public function test_shopper_can_store_an_enabled_currency_in_their_session(): void
    {
        $this->currencySettings();

        $this->postJson(route('storefront.currency.update'), ['currency' => 'usd'])
            ->assertNoContent()
            ->assertSessionHas('storefront_currency', 'USD');
    }

    public function test_shopper_cannot_store_a_disabled_currency_in_their_session(): void
    {
        $this->currencySettings();

        $this->postJson(route('storefront.currency.update'), ['currency' => 'GBP'])
            ->assertUnprocessable()
            ->assertSessionMissing('storefront_currency');
    }

    public function test_catalog_prices_render_with_the_enabled_session_currency(): void
    {
        $this->currencySettings();
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Currency Test Product', 'handle' => 'currency-test-product', 'brand' => 'JW', 'description' => 'Test', 'base_price' => 19900, 'status' => 'active']);
        $product->variants()->create(['sku' => 'CURRENCY-1', 'title' => 'Default', 'price' => 19900, 'stock' => 10, 'enabled' => true]);

        $this->withSession(['storefront_currency' => 'USD'])
            ->get(route('storefront.index'))
            ->assertOk()
            ->assertSee('$44.68');
    }

    public function test_cart_prices_use_the_enabled_session_currency(): void
    {
        $this->currencySettings();
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Cart Currency Product', 'handle' => 'cart-currency-product', 'brand' => 'JW', 'description' => 'Test', 'base_price' => 19900, 'status' => 'active']);
        $variant = $product->variants()->create(['sku' => 'CURRENCY-CART-1', 'title' => 'Default', 'price' => 19900, 'stock' => 10, 'enabled' => true]);

        $this->withSession(['storefront_currency' => 'USD', 'cart' => [$variant->id => 1]])
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('$44.68');
    }

    private function currencySettings(): void
    {
        StoreSetting::query()->create([
            'key' => 'currencies',
            'value' => ['data' => [
                ['code' => 'MYR', 'title' => 'Malaysian Ringgit', 'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1, 'isDefault' => true, 'isEnabled' => true],
                ['code' => 'USD', 'title' => 'US Dollar', 'symbolLeft' => '$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2245, 'isDefault' => false, 'isEnabled' => true],
                ['code' => 'GBP', 'title' => 'British Pound', 'symbolLeft' => '£', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.178, 'isDefault' => false, 'isEnabled' => false],
            ]],
        ]);
    }

    private function setStorefrontCurrency(string $code): void
    {
        $request = Request::create('/');
        $session = app('session')->driver();
        $session->start();
        $session->put('storefront_currency', $code);
        $request->setLaravelSession($session);
        app()->instance('request', $request);
    }
}
