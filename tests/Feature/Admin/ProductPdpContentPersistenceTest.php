<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPdpContentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_pdp_content_preserves_ordered_content_related_products_and_curated_reviews(): void
    {
        $product = $this->product('precision-eyewear');
        $related = $this->product('studio-headphones');

        $product->pdpContent()->create([
            'highlights' => [['id' => 'h-1', 'text' => 'Crafted from Japanese Beta-Titanium', 'badge' => 'PATENTED']],
            'specifications' => [['id' => 's-1', 'group' => 'Materials', 'label' => 'Frame', 'value' => 'Japanese Beta-Titanium']],
            'use_store_default_shipping' => false,
            'shipping_policy' => ['shippingBadgeText' => 'Complimentary Express Shipping Included'],
            'custom_accordions' => [['id' => 'a-1', 'title' => "What's In The Box", 'content' => 'Protective case']],
            'rating_score' => 4.9,
            'review_count' => 142,
            'configurator_label' => 'FINISH / OPTION',
        ]);
        $product->pdpRelatedProducts()->create(['related_product_id' => $related->id, 'sort_order' => 0]);
        $product->pdpReviews()->create([
            'author' => 'Ava Chen',
            'rating' => 5,
            'review_date' => '3 days ago',
            'title' => 'Optics are exceptionally clear',
            'comment' => 'The polarized lenses are crisp in bright daylight.',
            'verified_buyer' => true,
            'sort_order' => 0,
        ]);

        $fresh = $product->fresh(['pdpContent', 'pdpRelatedProducts', 'pdpReviews']);

        $this->assertSame('Crafted from Japanese Beta-Titanium', $fresh->pdpContent->highlights[0]['text']);
        $this->assertSame($related->id, $fresh->pdpRelatedProducts[0]->related_product_id);
        $this->assertSame('Ava Chen', $fresh->pdpReviews[0]->author);
        $this->assertSame('3 days ago', $fresh->pdpReviews[0]->review_date);
    }

    private function product(string $handle): Product
    {
        return Product::query()->create([
            'handle' => $handle,
            'title' => ucwords(str_replace('-', ' ', $handle)),
            'base_price' => 24500,
            'status' => 'active',
        ]);
    }
}
