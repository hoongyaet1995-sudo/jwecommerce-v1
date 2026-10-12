<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontQuickAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_plp_marks_multi_sku_products_for_the_source_faithful_quick_add_modal(): void
    {
        $product = $this->multiSkuProduct();

        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-quick-add-trigger', false)
            ->assertSee('data-quick-add-product-id="'.$product->id.'"', false)
            ->assertSee('data-quick-add-modal', false)
            ->assertSee('View Full Product Specifications &amp; Lab Tests', false)
            ->assertSee('referrerpolicy="no-referrer"', false)
            ->assertDontSee('Backorder Available')
            ->assertDontSee('Dispatches Today')
            ->assertDontSee('Verified Studio Reviews')
            ->assertDontSee('2-Year Atelier Warranty');
    }

    public function test_success_recommendations_use_the_same_quick_add_modal_contract_as_the_plp(): void
    {
        $product = $this->multiSkuProduct();
        $order = $this->order();

        $this->withSession(['last_order_id' => $order->id])
            ->get('/order/success')
            ->assertOk()
            ->assertSee('data-storefront-product-card', false)
            ->assertSee('data-quick-add-trigger', false)
            ->assertSee('data-quick-add-product-id="'.$product->id.'"', false)
            ->assertSee('data-quick-add-modal', false)
            ->assertDontSee('action="'.route('cart.items.store').'" class="mt-3"', false);
    }

    public function test_home_featured_products_use_the_same_quick_add_modal_contract_as_the_plp(): void
    {
        $product = $this->multiSkuProduct();

        $this->get('/')
            ->assertOk()
            ->assertSee('data-storefront-product-card', false)
            ->assertSee('data-quick-add-trigger', false)
            ->assertSee('data-quick-add-product-id="'.$product->id.'"', false)
            ->assertSee('data-quick-add-modal', false);
    }

    public function test_pdp_companion_products_use_the_same_quick_add_modal_contract_as_the_plp(): void
    {
        $companion = $this->multiSkuProduct();
        $primary = Product::query()->create([
            'handle' => 'quick-add-primary',
            'title' => 'Quick Add Primary',
            'base_price' => 19900,
            'status' => 'active',
        ]);
        $primary->variants()->create(['sku' => 'QUICK-PRIMARY', 'title' => 'Default', 'price' => 19900, 'stock' => 2, 'enabled' => true]);
        $primary->pdpRelatedProducts()->create(['related_product_id' => $companion->id, 'sort_order' => 0]);

        $this->get('/products/'.$primary->handle)
            ->assertOk()
            ->assertSee('data-storefront-product-card', false)
            ->assertSee('data-quick-add-trigger', false)
            ->assertSee('data-quick-add-product-id="'.$companion->id.'"', false)
            ->assertSee('data-quick-add-modal', false);
    }

    public function test_cart_quick_add_can_request_a_fresh_same_session_csrf_token(): void
    {
        $this->getJson('/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['token']);
    }
    public function test_home_deco_preserves_variation_and_cart_contract_with_authoritative_stock(): void
    {
        $product = $this->multiSkuProduct();
        app(\App\Services\WebsiteDesignService::class)->save(['theme'=>'home_deco','overrides'=>[]]);
        $variant = $product->variants()->where('sku', 'QUICK-IN-STOCK')->firstOrFail();
        $this->get('/shop')->assertOk()->assertSee('data-website-theme="home_deco"', false)
            ->assertSee('Buy Now')->assertSee('data-quick-add-modal', false)->assertSee('Product details');
        $this->postJson('/cart/items', ['variant_id'=>$variant->id,'quantity'=>1])->assertRedirect(route('cart.show'));
        $this->assertSame(1,session('cart')[$variant->id]);
        $this->get('/cart/drawer')->assertOk()->assertSee('data-home-deco-cart', false)->assertSee('data-cart-drawer-form', false)->assertSee('Checkout');
    }

    public function test_cart_store_rejects_an_out_of_stock_sku_without_creating_a_cart_line(): void
    {
        $product = $this->multiSkuProduct();
        $variant = $product->variants()->where('sku', 'QUICK-SOLD-OUT')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant_id');

        $this->assertNull(session('cart'));
    }

    public function test_cart_store_rejects_a_quantity_above_the_real_sku_stock(): void
    {
        $product = $this->multiSkuProduct();
        $variant = $product->variants()->where('sku', 'QUICK-IN-STOCK')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertNull(session('cart'));
    }

    public function test_cart_store_rejects_an_addition_that_would_take_an_existing_line_over_stock(): void
    {
        $product = $this->multiSkuProduct();
        $variant = $product->variants()->where('sku', 'QUICK-IN-STOCK')->firstOrFail();

        $this->withSession(['cart' => [$variant->id => 1]])
            ->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame([$variant->id => 1], session('cart'));
    }

    private function multiSkuProduct(): Product
    {
        $product = Product::query()->create([
            'handle' => 'quick-add-monitor',
            'title' => 'Quick Add Monitor',
            'subtitle' => 'Precision sound for the studio.',
            'brand' => 'ATELIER AUDIO',
            'description' => 'Quick Add source fixture.',
            'base_price' => 29900,
            'compare_at_price' => 34900,
            'status' => 'active',
            'has_variants' => true,
            'options' => [[
                'id' => 'finish',
                'name' => 'Finish',
                'displayType' => 'color_palette',
                'values' => ['Night Grey', 'Studio White'],
                'valueVisuals' => [
                    'Night Grey' => ['visualType' => 'color', 'colorCode' => '#64748B'],
                    'Studio White' => ['visualType' => 'image', 'optionImage' => '/images/catalog/white.jpg'],
                ],
            ]],
        ]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => '/images/catalog/hero.jpg', 'alt' => 'Quick Add Monitor', 'position' => 0]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => '/images/catalog/angle.jpg', 'alt' => 'Quick Add Monitor angle', 'position' => 1]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'QUICK-IN-STOCK',
            'title' => 'Night Grey',
            'options' => ['Finish' => 'Night Grey'],
            'price' => 29900,
            'compare_at_price' => 34900,
            'stock' => 2,
            'enabled' => true,
            'visual_type' => 'color',
            'color_code' => '#64748B',
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'QUICK-SOLD-OUT',
            'title' => 'Studio White',
            'options' => ['Finish' => 'Studio White'],
            'price' => 31900,
            'stock' => 0,
            'enabled' => true,
            'visual_type' => 'image',
            'option_image' => '/images/catalog/white.jpg',
        ]);

        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        DB::table('inventory_pools')->updateOrInsert(['pool_key' => 'shared'], ['kind' => 'shared', 'created_at' => now(), 'updated_at' => now()]);
        $poolId = DB::table('inventory_pools')->where('pool_key', 'shared')->value('id');
        foreach ($product->variants as $variant) {
            DB::table('inventory_balances')->insert(['inventory_pool_id' => $poolId, 'product_variant_id' => $variant->id, 'current_stock' => $variant->stock, 'created_at' => now(), 'updated_at' => now()]);
        }
        return $product->fresh('variants');
    }

    private function order(): Order
    {
        return Order::query()->create([
            'number' => 'JW-QUICK-ADD-001',
            'email' => 'buyer@example.test',
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
    }
}
