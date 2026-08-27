<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPackageSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_snapshots_variant_package_specification(): void
    {
        $order = $this->checkoutVariant([
            'weight_grams' => 420,
            'package_length_cm' => 30.5,
            'package_width_cm' => 12,
            'package_height_cm' => 8,
        ]);
        $item = $order->items()->sole();

        $this->assertSame([
            'weight_grams' => 420,
            'length_cm' => 30.5,
            'width_cm' => 12,
            'height_cm' => 8,
        ], $item->package_snapshot);

        $item->productVariant->update([
            'weight_grams' => 999,
            'package_length_cm' => 50,
            'package_width_cm' => 50,
            'package_height_cm' => 50,
        ]);

        $this->assertSame(420, $item->fresh()->package_snapshot['weight_grams']);
        $this->assertSame(30.5, $item->fresh()->package_snapshot['length_cm']);
    }

    public function test_checkout_snapshots_null_dimensions_for_legacy_variants(): void
    {
        $order = $this->checkoutVariant(['weight_grams' => 420]);

        $this->assertSame([
            'weight_grams' => 420,
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
        ], $order->items()->sole()->package_snapshot);
    }

    /** @param array<string, int|float|null> $package */
    private function checkoutVariant(array $package): Order
    {
        $category = Category::query()->create([
            'name' => 'Package category',
            'slug' => 'package-category-'.str()->random(8),
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Package test product',
            'handle' => 'package-test-product-'.str()->random(8),
            'description' => 'Package test product',
            'base_price' => 10000,
            'status' => 'active',
        ]);
        $variant = $product->variants()->create([
            'sku' => 'PACKAGE-'.str()->upper(str()->random(8)),
            'title' => 'Default',
            'price' => 10000,
            'stock' => 10,
            'enabled' => true,
        ] + $package);

        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'standard', 'name' => 'Standard Delivery', 'zoneRegion' => 'all', 'logisticType' => 'all',
            'calculationMode' => 'per_order', 'rateType' => 'flat', 'tiers' => [], 'flatRateCost' => 0,
            'freeShippingThreshold' => 0, 'isActive' => true,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'bankAccounts' => [['id' => 'primary', 'bankName' => 'Test Bank', 'accountNumber' => '1234', 'isActive' => true]],
        ]]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', [
                'email' => 'package-buyer@example.com', 'first_name' => 'Package', 'last_name' => 'Buyer',
                'line_1' => '10 Package Street', 'city' => 'Kuala Lumpur', 'state' => 'Selangor',
                'postal_code' => '50000', 'country' => 'MY', 'delivery' => 'standard', 'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect('/order/success');

        return Order::query()->with('items.productVariant')->sole();
    }
}
