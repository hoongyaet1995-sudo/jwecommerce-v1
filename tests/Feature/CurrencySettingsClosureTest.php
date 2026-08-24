<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySettingsClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_currency_configuration_is_persisted_in_the_commerce_settings_document(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $currencies = [
            ['id' => 'MYR', 'code' => 'MYR', 'title' => 'Malaysian Ringgit', 'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1, 'isDefault' => true, 'autoUpdate' => true, 'lastUpdated' => '2026-08-23 00:00:00 (Manual)', 'isEnabled' => true],
            ['id' => 'USD', 'code' => 'USD', 'title' => 'US Dollar', 'symbolLeft' => '$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2245, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-23 00:00:00 (Manual)', 'isEnabled' => true],
        ];

        $this->actingAs($admin)->putJson('/admin/api/commerce-settings', [
            'shippingRates' => [['id' => 'standard', 'title' => 'Standard Shipping']],
            'paymentGateways' => [['id' => 'bank-transfer', 'title' => 'Bank Transfer']],
            'globalPayment' => ['defaultGateway' => 'bank-transfer'],
            'currencies' => $currencies,
        ])->assertOk()
            ->assertJsonPath('data.currencies.0.code', 'MYR')
            ->assertJsonPath('data.currencies.1.code', 'USD');

        $storedCurrencies = data_get(StoreSetting::query()->where('key', 'currencies')->firstOrFail()->value, 'data');
        $this->assertSame('MYR', $storedCurrencies[0]['code']);
        $this->assertSame('RM', $storedCurrencies[0]['symbolLeft']);
        $this->assertSame('USD', $storedCurrencies[1]['code']);
    }

    public function test_storefront_currency_control_uses_only_enabled_persisted_currencies_and_the_default_selection(): void
    {
        StoreSetting::query()->create(['key' => 'currencies', 'value' => ['data' => [
            ['id' => 'MYR', 'code' => 'MYR', 'title' => 'Malaysian Ringgit', 'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1, 'isDefault' => true, 'autoUpdate' => true, 'lastUpdated' => '', 'isEnabled' => true],
            ['id' => 'USD', 'code' => 'USD', 'title' => 'US Dollar', 'symbolLeft' => '$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2245, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '', 'isEnabled' => true],
            ['id' => 'GBP', 'code' => 'GBP', 'title' => 'British Pound Sterling', 'symbolLeft' => '£', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.178, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '', 'isEnabled' => false],
        ]]]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('<template data-atelier-currency-options', false)
            ->assertSee('value="MYR" selected', false)
            ->assertSee('value="USD"', false)
            ->assertSee('class="bg-white text-zinc-900"', false)
            ->assertDontSee('value="GBP"', false);
    }
}
