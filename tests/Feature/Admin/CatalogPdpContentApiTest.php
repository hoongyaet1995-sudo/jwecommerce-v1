<?php

namespace Tests\Feature\Admin;

use App\Models\ProductPdpReview;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPdpContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_and_refetches_complete_pdp_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->category($admin);
        $related = Product::query()->create([
            'handle' => 'paired-product',
            'title' => 'Paired Product',
            'base_price' => 12900,
            'status' => 'active',
        ]);
        $payload = $this->productPayload((string) $category['id']);
        $payload['pdpContent'] = [
            'highlights' => [['id' => 'highlight-1', 'text' => 'Crafted from Japanese Beta-Titanium', 'badge' => 'ZEISS OPTICS']],
            'specifications' => [['id' => 'spec-1', 'group' => 'Materials', 'label' => 'Frame', 'value' => 'Japanese Beta-Titanium']],
            'useStoreDefaultShipping' => false,
            'shippingPolicy' => ['shippingBadgeText' => 'Complimentary Express Shipping Included', 'leadTimeText' => 'Dispatches today'],
            'customAccordions' => [['id' => 'accordion-1', 'title' => "What's In The Box", 'content' => 'Protective case']],
            'configuratorLabel' => 'FINISH / OPTION',
        ];
        $payload['crossSellProductIds'] = [(string) $related->id];
        $payload['reviews'] = [[
            'id' => 'review-1', 'author' => 'Ava Chen', 'rating' => 5, 'date' => '2026-08-22',
            'title' => 'Optics are exceptionally clear', 'comment' => 'The polarized lenses are crisp in bright daylight.',
            'verifiedBuyer' => true, 'variantPurchased' => 'Gunmetal Titanium', 'helpfulCount' => 7,
        ]];

        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.pdpContent.highlights.0.text', 'Crafted from Japanese Beta-Titanium')
            ->assertJsonPath('data.crossSellProductIds.0', (string) $related->id)
            ->json('data');

        $catalog = $this->actingAs($admin)->getJson('/admin/api/catalog')->assertOk()->json('products');
        $persisted = collect($catalog)->firstWhere('id', (string) $product['id']);

        $this->assertSame('Complimentary Express Shipping Included', $persisted['pdpContent']['shippingPolicy']['shippingBadgeText']);
        $this->assertArrayNotHasKey('ratingScore', $persisted['pdpContent']);
        $this->assertArrayNotHasKey('reviewCount', $persisted['pdpContent']);
        $this->assertArrayNotHasKey('reviews', $persisted);
        $this->assertSame(0, ProductPdpReview::query()->count());
    }

    public function test_catalog_rejects_self_archived_and_more_than_four_cross_sell_products(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->category($admin);
        $payload = $this->productPayload((string) $category['id']);
        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)->assertCreated()->json('data');

        $payload['variants'][0]['id'] = $product['variants'][0]['id'];
        $payload['crossSellProductIds'] = [(string) $product['id']];
        $this->actingAs($admin)->patchJson('/admin/api/catalog/products/'.$product['id'], $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('crossSellProductIds.0');
    }

    /** @return array<string, mixed> */
    private function category(User $admin): array
    {
        return $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Eyewear', 'slug' => 'eyewear', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');
    }

    /** @return array<string, mixed> */
    private function productPayload(string $categoryId): array
    {
        return [
            'title' => 'Titanium Precision Eyewear', 'slug' => 'titanium-precision-eyewear',
            'brand' => 'Optik Craft', 'categoryId' => $categoryId, 'collectionIds' => [],
            'summary' => 'Japanese Beta-Titanium frames.', 'descriptionHtml' => '<p>Precision eyewear.</p>',
            'images' => ['/images/catalog/aether-1.jpg'], 'status' => 'active', 'hasVariants' => true,
            'basePrice' => 245, 'baseCompareAtPrice' => 295, 'featured' => false,
            'tags' => ['eyewear'], 'options' => [['id' => 'finish', 'name' => 'Finish', 'values' => ['Gunmetal']]],
            'variants' => [[
                'id' => 'new-variant', 'sku' => 'OPT-001', 'title' => 'Gunmetal', 'options' => ['Finish' => 'Gunmetal'],
                'price' => 245, 'compareAtPrice' => 295, 'costPrice' => 100, 'inventoryQuantity' => 14,
                'weightGrams' => 28, 'barcode' => 'OPT001', 'image' => '/images/catalog/aether-1.jpg', 'enabled' => true,
            ]],
            'seoTitle' => 'Titanium Precision Eyewear', 'seoDescription' => 'Precision eyewear.',
        ];
    }
}
