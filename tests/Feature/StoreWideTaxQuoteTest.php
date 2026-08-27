<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\TaxClass;
use App\Services\CartPricingService;
use App\Services\TaxQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreWideTaxQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_class_taxes_discounted_merchandise_but_not_shipping_or_surcharge(): void
    {
        TaxClass::factory()->create([
            'name' => 'SST',
            'rate_percent' => '8.0000',
            'is_active' => true,
            'active_slot' => 1,
        ]);

        $coupon = Coupon::query()->create([
            'code' => 'TAX10',
            'discount_type' => 'fixed_amount',
            'value' => 1000,
            'is_active' => true,
        ]);

        $summary = app(CartPricingService::class)->summarize($this->itemsAt(10000), $coupon, 'standard', ['country' => 'MY'], 500);

        $this->assertSame(9000, $summary['tax_quote']['taxable_amount']);
        $this->assertSame(720, $summary['tax']);
        $this->assertSame('SST', $summary['tax_quote']['label']);
        $this->assertSame(500, $summary['payment_surcharge']);
        $this->assertSame(10220, $summary['total']);
    }

    public function test_no_active_class_and_active_zero_rate_are_distinct(): void
    {
        $items = $this->itemsAt(10000);
        $none = app(TaxQuoteService::class)->quote($items, null);

        TaxClass::factory()->create([
            'name' => 'Zero Rate',
            'rate_percent' => '0.0000',
            'is_active' => true,
            'active_slot' => 1,
        ]);
        $zero = app(TaxQuoteService::class)->quote($items, null);

        $this->assertSame('not_configured', $none['status']);
        $this->assertSame([], $none['lines']);
        $this->assertSame('matched_zero_rate', $zero['status']);
        $this->assertSame('Zero Rate', $zero['label']);
        $this->assertCount(1, $zero['lines']);
    }

    private function itemsAt(int $price): \Illuminate\Support\Collection
    {
        $category = Category::query()->create(['name' => 'Tax quote '.str()->random(8), 'slug' => str()->random(12), 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Tax quote product', 'handle' => str()->random(14), 'description' => 'Test', 'base_price' => $price, 'status' => 'active']);
        $variant = $product->variants()->create(['sku' => str()->random(12), 'title' => 'Default', 'price' => $price, 'stock' => 10, 'enabled' => true]);

        return collect([['variant' => $variant, 'quantity' => 1]]);
    }
}
