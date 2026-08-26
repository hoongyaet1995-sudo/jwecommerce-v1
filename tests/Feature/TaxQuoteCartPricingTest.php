<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\TaxConfiguration;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Services\CartPricingService;
use App\Services\TaxQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TaxQuoteCartPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_or_unpublished_configuration_returns_a_zero_quote(): void
    {
        [$items, $class, $zone] = $this->taxableCart('MY', 'Selangor', '50000');
        TaxConfiguration::factory()->create(['is_enabled' => false, 'is_published' => true]);
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id, 'rate_percent' => '8.0000', 'is_enabled' => true]);

        $quote = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000']);

        $this->assertSame(0, $quote['tax']);
        $this->assertSame('configuration_inactive', $quote['status']);
    }

    public function test_an_enabled_but_unpublished_configuration_returns_a_zero_quote(): void
    {
        [$items, $class, $zone] = $this->taxableCart('MY', 'Selangor', '50000');
        TaxConfiguration::factory()->create(['is_enabled' => true, 'is_published' => false]);
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id, 'rate_percent' => '8.0000', 'is_enabled' => true]);

        $quote = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000']);

        $this->assertSame(0, $quote['tax']);
        $this->assertSame('configuration_inactive', $quote['status']);
    }

    public function test_an_exact_destination_rule_calculates_an_integer_cent_merchandise_quote(): void
    {
        [$items, $class, $zone] = $this->taxableCart('MY', 'Selangor', '50000', 12345);
        $this->publishConfiguration();
        $rule = TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id, 'rate_percent' => '6.0000', 'is_enabled' => true, 'label' => 'MY exact tax']);

        $quote = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000']);

        $this->assertSame(740, $quote['tax']);
        $this->assertSame('chargeable', $quote['status']);
        $this->assertSame($rule->id, $quote['lines'][0]['rule_id']);
        $this->assertSame(12345, $quote['lines'][0]['taxable_amount']);
    }

    public function test_classless_products_are_non_taxable_and_legacy_store_rates_do_not_leak(): void
    {
        $items = $this->cartForProduct($this->product(null, 10000));
        $this->publishConfiguration();
        \App\Models\StoreSetting::query()->create(['key' => 'tax_rate_percent', 'value' => ['data' => 99]]);

        $quote = app(TaxQuoteService::class)->quote($items, ['country' => 'MY']);
        $summary = app(CartPricingService::class)->summarize($items, null, 'standard', ['country' => 'MY']);

        $this->assertSame(0, $quote['tax']);
        $this->assertSame('no_matching_rules', $quote['status']);
        $this->assertSame(0, $summary['tax']);
        $this->assertSame($quote, $summary['tax_quote']);
    }

    public function test_more_specific_zone_then_priority_resolves_deterministically(): void
    {
        [$items, $class] = $this->taxableCart('MY', 'Selangor', '50000');
        $this->publishConfiguration();
        $broad = TaxZone::factory()->create(['country' => 'MY', 'state' => null, 'postcode' => null]);
        $specific = TaxZone::factory()->create(['country' => 'MY', 'state' => 'Selangor', 'postcode' => '50000']);
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $broad->id, 'rate_percent' => '5.0000', 'priority' => 1, 'is_enabled' => true]);
        $chosen = TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $specific->id, 'rate_percent' => '6.0000', 'priority' => 1, 'is_enabled' => true]);
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $specific->id, 'rate_percent' => '7.0000', 'priority' => 2, 'is_enabled' => true]);

        $quote = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000']);

        $this->assertSame($chosen->id, $quote['lines'][0]['rule_id']);
        $this->assertSame(600, $quote['tax']);
    }

    public function test_shipping_is_taxed_only_when_a_matching_rule_explicitly_allows_it(): void
    {
        [$items, $class, $zone] = $this->taxableCart('MY', 'Selangor', '50000');
        $this->publishConfiguration();
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id, 'rate_percent' => '6.0000', 'applies_to_shipping' => false, 'is_enabled' => true]);

        $withoutShippingTax = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000'], 1000);
        TaxRule::query()->update(['applies_to_shipping' => true]);
        $withShippingTax = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000'], 1000);

        $this->assertSame(600, $withoutShippingTax['tax']);
        $this->assertSame(660, $withShippingTax['tax']);
        $this->assertSame('shipping', $withShippingTax['lines'][1]['kind']);
    }

    public function test_cart_pricing_calculates_tax_after_a_fixed_merchandise_discount(): void
    {
        [$items, $class, $zone] = $this->taxableCart('MY', 'Selangor', '50000');
        $this->publishConfiguration();
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id, 'rate_percent' => '6.0000', 'is_enabled' => true]);
        $coupon = Coupon::query()->create(['code' => 'TENOFF', 'name' => 'Ten off', 'discount_type' => 'fixed_amount', 'value' => 1000, 'usage_limit' => 1, 'usage_count' => 0, 'per_customer_limit' => 1, 'is_active' => true]);

        $summary = app(CartPricingService::class)->summarize($items, $coupon, 'standard', ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000']);

        $this->assertSame(1000, $summary['discount']);
        $this->assertSame(540, $summary['tax']);
        $this->assertSame(9000, $summary['tax_quote']['lines'][0]['taxable_amount']);
    }

    public function test_discount_remainder_is_allocated_deterministically_across_taxable_merchandise_lines(): void
    {
        $class = TaxClass::factory()->create();
        $zone = TaxZone::factory()->create(['country' => 'MY', 'state' => 'Selangor', 'postcode' => '50000']);
        $items = collect([
            ['variant' => $this->product($class, 10001)->variants->sole(), 'quantity' => 1],
            ['variant' => $this->product($class, 10000)->variants->sole(), 'quantity' => 1],
        ]);
        $this->publishConfiguration();
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id, 'rate_percent' => '6.0000', 'is_enabled' => true]);

        $quote = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000'], 0, 1);

        $this->assertSame([10000, 10000], array_column($quote['lines'], 'taxable_amount'));
        $this->assertSame(1200, $quote['tax']);
    }

    public function test_an_explicit_matching_zero_rate_rule_has_a_distinguishable_matched_status(): void
    {
        [$items, $class, $zone] = $this->taxableCart('MY', 'Selangor', '50000');
        $this->publishConfiguration();
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id, 'rate_percent' => '0.0000', 'is_enabled' => true]);

        $quote = app(TaxQuoteService::class)->quote($items, ['country' => 'MY', 'state' => 'Selangor', 'postal_code' => '50000']);

        $this->assertSame(0, $quote['tax']);
        $this->assertSame('matched_zero_rate', $quote['status']);
        $this->assertSame('matched_zero_rate', $quote['lines'][0]['status']);
    }

    private function publishConfiguration(): void
    {
        TaxConfiguration::factory()->create(['is_enabled' => true, 'is_published' => true]);
    }

    private function taxableCart(string $country, ?string $state = null, ?string $postcode = null, int $price = 10000): array
    {
        $class = TaxClass::factory()->create();
        $zone = TaxZone::factory()->create(['country' => $country, 'state' => $state, 'postcode' => $postcode]);

        return [$this->cartForProduct($this->product($class, $price)), $class, $zone];
    }

    private function product(?TaxClass $class, int $price): Product
    {
        $category = Category::query()->create(['name' => 'Tax test '.str()->random(8), 'slug' => str()->random(12), 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'tax_class_id' => $class?->id, 'title' => 'Tax test product', 'handle' => str()->random(14), 'description' => 'Test', 'base_price' => $price, 'status' => 'active']);
        $product->variants()->create(['sku' => str()->random(12), 'title' => 'Default', 'price' => $price, 'stock' => 10, 'enabled' => true]);

        return $product->fresh('variants');
    }

    private function cartForProduct(Product $product): Collection
    {
        return collect([['variant' => $product->variants->sole(), 'quantity' => 1]]);
    }
}
