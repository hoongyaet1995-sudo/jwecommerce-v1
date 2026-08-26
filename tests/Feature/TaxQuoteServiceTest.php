<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxQuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_has_an_explicit_tax_class_and_orders_retain_tax_lines(): void
    {
        $class = TaxClass::factory()->create(['code' => 'standard-goods']);
        $product = Product::factory()->create(['tax_class_id' => $class->id]);
        $order = Order::factory()->create();
        $line = OrderTaxLine::factory()->create([
            'order_id' => $order->id,
            'tax_class_id' => $class->id,
            'taxable_amount' => 10000,
            'tax_amount' => 600,
        ]);

        $this->assertTrue($product->taxClass->is($class));
        $this->assertTrue($order->taxLines->first()->is($line));
    }

    public function test_a_tax_rule_requires_all_configured_address_selectors_to_match(): void
    {
        $zone = TaxZone::factory()->create([
            'country' => 'MY',
            'state' => 'Selangor',
            'postcode' => '47301',
        ]);
        $rule = TaxRule::factory()->create(['tax_zone_id' => $zone->id]);

        $this->assertTrue($rule->matchesAddress([
            'country' => 'my',
            'state' => 'selangor',
            'postal_code' => '47301',
        ]));
        $this->assertFalse($rule->matchesAddress([
            'country' => 'MY',
            'state' => 'Selangor',
            'postal_code' => '50000',
        ]));
    }

    public function test_a_tax_rule_is_effective_only_inside_its_configured_time_bounds(): void
    {
        $at = CarbonImmutable::parse('2026-08-26 12:00:00');
        $rule = TaxRule::factory()->create([
            'effective_from' => $at->subDay(),
            'effective_until' => $at->addDay(),
        ]);

        $this->assertTrue($rule->isEffectiveAt($at));
        $this->assertFalse($rule->isEffectiveAt($at->subDays(2)));
        $this->assertFalse($rule->isEffectiveAt($at->addDays(2)));
    }
}

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $handle = fake()->unique()->slug(3);

        return [
            'category_id' => null,
            'tax_class_id' => null,
            'handle' => $handle,
            'title' => Str::headline($handle),
            'subtitle' => null,
            'brand' => null,
            'description' => null,
            'base_price' => 1000,
            'compare_at_price' => null,
            'features' => null,
            'status' => 'draft',
            'is_featured' => false,
            'has_variants' => false,
            'options' => null,
            'tags' => null,
            'seo_title' => null,
            'seo_description' => null,
        ];
    }
}

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->bothify('ORD-########'),
            'customer_id' => null,
            'coupon_id' => null,
            'coupon_code' => null,
            'email' => fake()->safeEmail(),
            'currency' => 'USD',
            'currency_exchange_rate' => '1.00000000',
            'status' => 'pending_payment',
            'shipping_method' => 'standard',
            'shipping_label' => null,
            'shipping_snapshot' => null,
            'tracking_carrier' => null,
            'tracking_number' => null,
            'customer_note' => null,
            'internal_note' => null,
            'shipped_at' => null,
            'subtotal' => 0,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'payment_surcharge_amount' => 0,
            'tax_amount' => 0,
            'total' => 0,
        ];
    }
}
