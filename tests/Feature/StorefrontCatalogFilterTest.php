<?php

namespace Tests\Feature;

use App\Services\CatalogFilterService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class StorefrontCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_range_excludes_disabled_matching_variants(): void
    {
        $category = Category::query()->create([
            'name' => 'Filter category',
            'slug' => 'filter-category',
            'is_active' => true,
        ]);

        $visible = $this->productWithVariant($category, 'enabled-price-match', 'Enabled price match', [
            'price' => 25000,
            'enabled' => true,
        ]);
        $hidden = $this->productWithVariant($category, 'disabled-price-match', 'Disabled price match', [
            'price' => 25000,
            'enabled' => false,
        ]);

        $this->get('/shop?min_price=20000&max_price=30000&price_filter=1')
            ->assertOk()
            ->assertSee($visible->title)
            ->assertDontSee($hidden->title);
    }

    public function test_color_and_material_must_match_the_same_enabled_variant(): void
    {
        $category = Category::query()->create(['name' => 'Material category', 'slug' => 'material-category', 'is_active' => true]);
        $match = $this->productWithVariant($category, 'black-titanium', 'Black Titanium', [
            'price' => 30000, 'enabled' => true, 'color_name' => 'Black', 'color_hex' => '#000000', 'material' => 'Titanium',
        ]);
        $split = $this->productWithVariant($category, 'split-variant', 'Split Variant', [
            'price' => 30000, 'enabled' => true, 'color_name' => 'Black', 'material' => 'Leather',
        ]);
        $split->variants()->create([
            'sku' => 'SPLIT-TITANIUM', 'title' => 'Silver Titanium', 'price' => 30000, 'stock' => 10,
            'enabled' => true, 'color_name' => 'Silver', 'material' => 'Titanium',
        ]);

        $this->get('/shop?color[]=Black&material[]=Titanium')
            ->assertOk()
            ->assertSee($match->title)
            ->assertDontSee($split->title);
    }

    public function test_rating_filter_uses_only_published_order_linked_reviews(): void
    {
        $category = Category::query()->create(['name' => 'Rating category', 'slug' => 'rating-category', 'is_active' => true]);
        $published = $this->productWithVariant($category, 'published-rating', 'Published Rating Product', ['price' => 30000, 'enabled' => true]);
        $pending = $this->productWithVariant($category, 'pending-rating', 'Pending Rating Product', ['price' => 30000, 'enabled' => true]);

        $this->reviewFor($published, 'published', 5);
        $this->reviewFor($pending, 'pending', 5);

        $this->assertDatabaseHas('product_reviews', ['product_id' => $published->id, 'status' => 'published', 'rating' => 5]);
        $this->assertSame([$published->id], ProductReview::query()->published()
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('AVG(rating) * 10 >= ?', [48])
            ->pluck('product_id')
            ->all());

        $this->get('/shop?min_rating=4.8')
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($pending->title);
    }

    public function test_real_variant_facets_render_database_materials_and_counts(): void
    {
        $category = Category::query()->create(['name' => 'Facet category', 'slug' => 'facet-category', 'is_active' => true]);
        $this->productWithVariant($category, 'facet-black-titanium', 'Facet Black Titanium', [
            'price' => 30000, 'enabled' => true, 'color_name' => 'Black', 'material' => 'Titanium',
        ]);
        $this->productWithVariant($category, 'facet-black-leather', 'Facet Black Leather', [
            'price' => 30000, 'enabled' => true, 'color_name' => 'Black', 'material' => 'Leather',
        ]);

        $this->get('/shop?color[]=Black')
            ->assertOk()
            ->assertSee('Titanium')
            ->assertSee('Leather');
    }

    public function test_category_facet_counts_retain_the_other_active_filters(): void
    {
        $audio = Category::query()->create(['name' => 'Audio facet', 'slug' => 'audio-facet', 'is_active' => true]);
        $desk = Category::query()->create(['name' => 'Desk facet', 'slug' => 'desk-facet', 'is_active' => true]);
        $this->productWithVariant($audio, 'audio-black', 'Audio Black', [
            'price' => 30000, 'enabled' => true, 'color_name' => 'Black', 'material' => 'Titanium',
        ]);
        $this->productWithVariant($desk, 'desk-black', 'Desk Black', [
            'price' => 30000, 'enabled' => true, 'color_name' => 'Black', 'material' => 'Wood',
        ]);
        $this->productWithVariant($desk, 'desk-silver', 'Desk Silver', [
            'price' => 30000, 'enabled' => true, 'color_name' => 'Silver', 'material' => 'Wood',
        ]);

        $result = app(CatalogFilterService::class)->for(
            Request::create('/shop', 'GET', ['color' => ['Black']]),
            Product::query()->where('status', 'active'),
        );

        $this->assertSame(1, $result->facets['categoryCounts'][$audio->id]);
        $this->assertSame(1, $result->facets['categoryCounts'][$desk->id]);
    }

    public function test_catalog_pagination_preserves_filter_query_state(): void
    {
        $category = Category::query()->create(['name' => 'Pagination category', 'slug' => 'pagination-category', 'is_active' => true]);
        foreach (range(1, 13) as $number) {
            $this->productWithVariant($category, 'page-product-'.$number, 'Page Product '.$number, [
                'price' => 10000 + $number, 'enabled' => true, 'material' => 'Pagination Material',
            ]);
        }

        $this->get('/shop?material[]=Pagination%20Material')
            ->assertOk()
            ->assertSee('Page Product 1')
            ->assertDontSee('Page Product 13')
            ->assertSee('page=2', false);

        $this->get('/shop?material[]=Pagination%20Material&page=2')
            ->assertOk()
            ->assertSee('Page Product 13');
    }

    private function productWithVariant(Category $category, string $handle, string $title, array $variant): Product
    {
        $product = Product::query()->create([
            'category_id' => $category->id,
            'handle' => $handle,
            'title' => $title,
            'base_price' => $variant['price'],
            'status' => 'active',
        ]);

        $product->variants()->create([
            'sku' => strtoupper($handle),
            'title' => 'Default',
            'price' => $variant['price'],
            'stock' => $variant['stock'] ?? 10,
            'enabled' => $variant['enabled'],
            'color_name' => $variant['color_name'] ?? null,
            'color_hex' => $variant['color_hex'] ?? null,
            'material' => $variant['material'] ?? null,
        ]);

        return $product;
    }

    private function reviewFor(Product $product, string $status, int $rating): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create(['user_id' => $user->id, 'email' => $user->email, 'first_name' => 'PLP', 'last_name' => 'Buyer']);
        $variant = $product->variants()->firstOrFail();
        $order = Order::query()->create([
            'number' => 'PLP-'.str()->uuid(), 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'delivered', 'subtotal' => $variant->price, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 0, 'total' => $variant->price,
        ]);
        $item = $order->items()->create([
            'product_variant_id' => $variant->id, 'sku' => $variant->sku, 'title' => $product->title,
            'variant_title' => $variant->title, 'unit_price' => $variant->price, 'quantity' => 1, 'line_total' => $variant->price,
        ]);
        ProductReview::query()->create([
            'order_item_id' => $item->id, 'product_id' => $product->id, 'customer_id' => $customer->id,
            'attempt_number' => 1, 'status' => $status, 'active_lock' => 'active', 'rating' => $rating,
            'title' => $status.' review', 'comment' => 'Only published reviews may affect PLP filtering.',
            'order_number_snapshot' => $order->number, 'product_title_snapshot' => $product->title,
            'customer_name_snapshot' => 'PLP Buyer', 'submitted_at' => now(),
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }
}
