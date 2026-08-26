<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\TaxClass;
use App\Models\TaxConfiguration;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCheckoutSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_persists_aggregate_tax_and_an_immutable_snapshot_for_each_matched_line(): void
    {
        [$variant, $class, $zone] = $this->taxableVariant();
        TaxConfiguration::factory()->create(['is_enabled' => true, 'is_published' => true, 'address_basis' => 'shipping']);
        $rule = TaxRule::factory()->create([
            'tax_class_id' => $class->id,
            'tax_zone_id' => $zone->id,
            'label' => 'Malaysia SST',
            'tax_type' => 'sales_tax',
            'rate_percent' => '6.0000',
            'is_enabled' => true,
        ]);

        $this->placeBankTransferOrder($variant->id);

        $order = Order::query()->sole();
        $line = $order->taxLines()->sole();
        $this->assertSame(600, $order->tax_amount);
        $this->assertSame($rule->id, $line->tax_rule_id);
        $this->assertSame($class->id, $line->tax_class_id);
        $this->assertSame('Malaysia SST', $line->label);
        $this->assertSame('sales_tax', $line->tax_type);
        $this->assertSame('6.0000', $line->rate_percent);
        $this->assertSame(10000, $line->taxable_amount);
        $this->assertSame(600, $line->tax_amount);
        $this->assertSame('SST', $line->configuration_snapshot['tax_class']['code']);
        $this->assertSame('MY-SELANGOR-50000', $line->configuration_snapshot['tax_zone']['code']);
        $this->assertSame('chargeable', $line->configuration_snapshot['quote_line']['status']);
    }

    public function test_a_matching_zero_percent_rule_is_snapshot_even_though_no_tax_is_due(): void
    {
        [$variant, $class, $zone] = $this->taxableVariant();
        TaxConfiguration::factory()->create(['is_enabled' => true, 'is_published' => true]);
        TaxRule::factory()->create([
            'tax_class_id' => $class->id,
            'tax_zone_id' => $zone->id,
            'rate_percent' => '0.0000',
            'is_enabled' => true,
        ]);

        $this->placeBankTransferOrder($variant->id);

        $order = Order::query()->sole();
        $line = $order->taxLines()->sole();
        $this->assertSame(0, $order->tax_amount);
        $this->assertSame(0, $line->tax_amount);
        $this->assertSame('0.0000', $line->rate_percent);
        $this->assertSame('matched_zero_rate', $line->configuration_snapshot['quote_line']['status']);
    }

    public function test_unpublished_or_unmatched_tax_configuration_creates_no_snapshot_lines(): void
    {
        [$variant, $class, $zone] = $this->taxableVariant();
        TaxConfiguration::factory()->create(['is_enabled' => false, 'is_published' => true]);
        TaxRule::factory()->create([
            'tax_class_id' => $class->id,
            'tax_zone_id' => $zone->id,
            'rate_percent' => '6.0000',
            'is_enabled' => true,
        ]);

        $this->placeBankTransferOrder($variant->id);

        $this->assertDatabaseHas('orders', ['tax_amount' => 0]);
        $this->assertDatabaseCount('order_tax_lines', 0);
    }

    public function test_tax_snapshot_survives_tax_rule_and_class_deletion(): void
    {
        [$variant, $class, $zone] = $this->taxableVariant();
        TaxConfiguration::factory()->create(['is_enabled' => true, 'is_published' => true]);
        $rule = TaxRule::factory()->create([
            'tax_class_id' => $class->id,
            'tax_zone_id' => $zone->id,
            'label' => 'Historical SST',
            'rate_percent' => '6.0000',
            'is_enabled' => true,
        ]);

        $this->placeBankTransferOrder($variant->id);
        $line = OrderTaxLine::query()->sole();
        $snapshot = $line->configuration_snapshot;

        $rule->update(['label' => 'Changed after checkout', 'rate_percent' => '10.0000']);
        $this->assertSame($snapshot, $line->fresh()->configuration_snapshot);
        $rule->delete();
        $class->delete();

        $line->refresh();
        $this->assertNull($line->tax_rule_id);
        $this->assertNull($line->tax_class_id);
        $this->assertSame($snapshot, $line->configuration_snapshot);
        $this->assertSame('Historical SST', $line->configuration_snapshot['tax_rule']['label']);
        $this->assertSame('SST', $line->configuration_snapshot['tax_class']['code']);
    }

    private function placeBankTransferOrder(int $variantId): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'standard', 'name' => 'Standard Delivery', 'zoneRegion' => 'all', 'logisticType' => 'all',
            'calculationMode' => 'per_order', 'rateType' => 'flat', 'tiers' => [], 'flatRateCost' => 0,
            'freeShippingThreshold' => 0, 'isActive' => true,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'bankAccounts' => [['id' => 'primary', 'bankName' => 'Test Bank', 'accountNumber' => '1234', 'isActive' => true]],
        ]]]);

        $this->withSession(['cart' => [$variantId => 1]])
            ->post('/checkout', [
                'email' => 'tax-buyer@example.com', 'first_name' => 'Tax', 'last_name' => 'Buyer',
                'line_1' => '10 Tax Street', 'city' => 'Kuala Lumpur', 'state' => 'Selangor',
                'postal_code' => '50000', 'country' => 'MY', 'delivery' => 'standard', 'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect('/order/success');
    }

    private function taxableVariant(): array
    {
        $class = TaxClass::query()->create(['code' => 'SST', 'name' => 'SST goods', 'is_active' => true]);
        $zone = TaxZone::query()->create(['code' => 'MY-SELANGOR-50000', 'name' => 'Selangor', 'country' => 'MY', 'state' => 'Selangor', 'postcode' => '50000', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Tax Category', 'slug' => 'tax-category', 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'tax_class_id' => $class->id, 'title' => 'Tax product', 'handle' => 'tax-product', 'description' => 'Tax test', 'base_price' => 10000, 'status' => 'active']);

        return [$product->variants()->create(['sku' => 'TAX-1', 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]), $class, $zone];
    }
}
