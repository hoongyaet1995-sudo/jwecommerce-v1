<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\TaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCheckoutSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_persists_the_active_store_wide_class_as_an_immutable_snapshot(): void
    {
        $class = TaxClass::query()->create([
            'code' => 'sst',
            'name' => 'SST',
            'rate_percent' => '8.0000',
            'is_active' => true,
            'active_slot' => 1,
        ]);

        $this->placeBankTransferOrder($this->variant()->id);

        $order = Order::query()->sole();
        $line = $order->taxLines()->sole();

        $this->assertSame(800, $order->tax_amount);
        $this->assertNull($line->tax_rule_id);
        $this->assertSame($class->id, $line->tax_class_id);
        $this->assertSame('SST', $line->label);
        $this->assertSame('store_wide_percentage', $line->tax_type);
        $this->assertSame('8.0000', $line->rate_percent);
        $this->assertSame(10000, $line->taxable_amount);
        $this->assertSame(800, $line->tax_amount);
        $this->assertSame('SST', $line->configuration_snapshot['tax_class']['name']);
        $this->assertSame('store_wide_percentage', $line->configuration_snapshot['calculation_mode']);

        $class->update(['name' => 'GST', 'rate_percent' => '6.0000']);

        $this->assertSame('SST', $line->fresh()->configuration_snapshot['tax_class']['name']);
        $this->assertSame('8.0000', $line->fresh()->configuration_snapshot['tax_class']['rate_percent']);
    }

    public function test_active_zero_rate_is_snapshot_but_unconfigured_tax_is_not(): void
    {
        $this->placeBankTransferOrder($this->variant()->id);
        $this->assertDatabaseCount('order_tax_lines', 0);

        Order::query()->delete();
        TaxClass::query()->create([
            'code' => 'zero',
            'name' => 'Zero Rate',
            'rate_percent' => '0.0000',
            'is_active' => true,
            'active_slot' => 1,
        ]);

        $this->placeBankTransferOrder($this->variant()->id);

        $line = OrderTaxLine::query()->sole();
        $this->assertSame(0, $line->tax_amount);
        $this->assertSame('matched_zero_rate', $line->configuration_snapshot['quote_line']['status']);
    }

    public function test_order_success_uses_the_frozen_tax_class_label(): void
    {
        TaxClass::query()->create([
            'code' => 'sst',
            'name' => 'SST',
            'rate_percent' => '8.0000',
            'is_active' => true,
            'active_slot' => 1,
        ]);

        $this->placeBankTransferOrder($this->variant()->id);
        $order = Order::query()->sole();

        $this->withSession(['last_order_id' => $order->id])
            ->get('/order/success')
            ->assertOk()
            ->assertSee('SST (8%)');
    }

    private function variant(): \App\Models\ProductVariant
    {
        $category = Category::query()->create(['name' => 'Tax Category '.str()->random(8), 'slug' => str()->random(12), 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Tax product', 'handle' => str()->random(14), 'description' => 'Tax test', 'base_price' => 10000, 'status' => 'active']);

        return $product->variants()->create(['sku' => str()->random(12), 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]);
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
}
