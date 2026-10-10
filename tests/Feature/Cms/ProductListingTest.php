<?php

namespace Tests\Feature\Cms;

use App\Domain\Cms\RuntimeAdapterRegistry;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editorial_manual_feed_publishes_live_catalog_and_does_not_mount_unused_purchase_ui(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $product=Product::create(['title'=>'Editorial chair','handle'=>'editorial-chair','status'=>'active','base_price'=>1200]);
        $product->variants()->create(['sku'=>'EDITORIAL','title'=>'Natural','price'=>1200,'stock'=>3,'enabled'=>true]);
        $section=['id'=>'editorial','type'=>'product_listing','title'=>'Objects','isVisible'=>true,'listingPreset'=>'editorial-grid','sourceType'=>'manual','productIds'=>[(string)$product->id],'productLimit'=>4,'columnsCount'=>4,'imageRatio'=>'1:1','buttonText'=>'Shop all products','buttonLink'=>'/shop'];
        $page=$this->postJson('/admin/api/pages',['title'=>'Editorial','slug'=>'editorial-check','template'=>'landing','sections'=>[$section]])->assertCreated()->json('data');
        $this->postJson('/admin/api/pages/'.$page['id'].'/publish',['lockVersion'=>$page['lockVersion']])->assertOk();
        $response=$this->get('/pages/editorial-check')->assertOk()->assertSee('editorial-grid')->assertSee('Editorial chair')->assertDontSee('data-quick-add-modal',false);
        $this->assertStringContainsString('"loginUrl":"/account/login"',$response->getContent());
    }

    public function test_collection_limit_empty_source_and_hidden_sections_have_no_catalog_fallback(): void
    {
        $collection = Collection::create(['title' => 'Selected', 'slug' => 'selected', 'is_active' => true, 'status' => 'active']);
        foreach (range(1, 5) as $index) {
            $product = Product::create(['title' => 'Item '.$index, 'handle' => 'item-'.$index, 'status' => 'active', 'base_price' => 1000]);
            $collection->products()->attach($product);
        }
        $section = ['id' => 'listing', 'type' => 'product_listing', 'isVisible' => true, 'sourceType' => 'collection', 'sourceId' => (string) $collection->id, 'productLimit' => 3];
        $facts = app(RuntimeAdapterRegistry::class)->factsFor(['sections' => [$section]]);
        $this->assertCount(3, $facts['sections'][0]['listing']['products']);
        $this->assertFalse($facts['sections'][0]['listing']['products'][0]['commerce']['canChoose']);
        $collection->products()->detach();
        $empty = app(RuntimeAdapterRegistry::class)->factsFor(['sections' => [$section]]);
        $this->assertTrue($empty['sections'][0]['listing']['sourceId']['valid']);
        $this->assertSame([], $empty['sections'][0]['listing']['products']);
        $collection->update(['is_active' => false, 'status' => 'archived']);
        $inactive = app(RuntimeAdapterRegistry::class)->factsFor(['sections' => [$section]]);
        $this->assertFalse($inactive['sections'][0]['listing']['sourceId']['valid']);
        $section['isVisible'] = false;
        $hidden = app(RuntimeAdapterRegistry::class)->factsFor(['sections' => [$section]]);
        $this->assertEmpty($hidden['sections']);
    }

    public function test_public_listing_mounts_existing_quick_add_and_draft_preview_cannot_add(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $category = Category::create(['name' => 'Shop', 'slug' => 'shop', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'title' => 'One', 'handle' => 'one', 'status' => 'active', 'base_price' => 1200]);
        $product->variants()->create(['sku' => 'ONE', 'title' => 'Black', 'price' => 1200, 'stock' => 3, 'enabled' => true]);
        $section = ['id' => 'listing', 'type' => 'product_listing', 'title' => 'Shop', 'isVisible' => true, 'sourceType' => 'category', 'sourceId' => (string) $category->id, 'productLimit' => 3];
        $page = $this->postJson('/admin/api/pages', ['title' => 'Listing', 'slug' => 'listing-check', 'template' => 'landing', 'sections' => [$section, [...$section, 'id' => 'listing-copy']]])->assertCreated()->json('data');
        $this->postJson('/admin/api/pages/'.$page['id'].'/publish', ['lockVersion' => $page['lockVersion']])->assertOk();
        $response = $this->get('/pages/listing-check')->assertOk()->assertSee('data-quick-add-modal', false)->assertSee('data-quick-add-options', false)->assertSee('ONE');
        $this->assertSame(1, substr_count($response->getContent(), 'data-quick-add-modal'));
        $this->get('/admin/pages/'.$page['id'].'/preview')->assertOk()->assertDontSee('data-quick-add-modal', false);
    }

    public function test_listing_settings_round_trip_and_empty_source_can_publish(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $section = ['id' => 'listing', 'type' => 'product_listing', 'title' => 'Shop', 'isVisible' => true, 'sourceType' => 'category', 'sourceId' => '', 'productLimit' => 12, 'autoSlide' => false, 'autoSlideInterval' => 3.5, 'showNavArrows' => false, 'columnsCount' => 4];
        $page = $this->postJson('/admin/api/pages', ['title' => 'Listing', 'slug' => 'listing', 'template' => 'landing', 'sections' => [$section]])->assertCreated()->json('data');
        $this->getJson('/admin/api/pages/'.$page['id'].'/draft')->assertOk()->assertJsonPath('data.document.sections.0.autoSlide', false)->assertJsonPath('data.document.sections.0.productLimit', 12);
        $this->postJson('/admin/api/pages/'.$page['id'].'/publish', ['lockVersion' => $page['lockVersion']])->assertOk();
        $this->get('/pages/listing')->assertOk();
        $section['autoSlideInterval'] = 0;
        $this->postJson('/admin/api/pages', ['title' => 'Invalid', 'slug' => 'invalid', 'template' => 'landing', 'sections' => [$section]])->assertUnprocessable();
    }

    public function test_listing_uses_real_variant_price_and_never_falls_back_to_all_products(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Real', 'handle' => 'real', 'status' => 'active', 'base_price' => 1000]);
        $variant = $product->variants()->create(['sku' => 'REAL-1', 'title' => 'Black', 'price' => 1200, 'compare_at_price' => null, 'stock' => 3, 'enabled' => true]);
        Product::create(['category_id' => $category->id, 'title' => 'Hidden', 'handle' => 'hidden', 'status' => 'draft', 'base_price' => 1000]);
        $section = ['id' => 'listing', 'type' => 'product_listing', 'title' => 'Shop', 'isVisible' => true, 'sourceType' => 'category', 'sourceId' => (string) $category->id, 'productLimit' => 3];
        $facts = app(RuntimeAdapterRegistry::class)->factsFor(['sections' => [$section]]);
        $listing = $facts['sections'][0]['listing'];
        $this->assertTrue($listing['sourceId']['valid']);
        $this->assertCount(1, $listing['products']);
        $this->assertSame('REAL-1', $listing['products'][0]['commerce']['sku']);
        $this->assertSame((string) $variant->id, $listing['products'][0]['commerce']['variants'][0]['id']);
        $this->assertSame(3, $listing['products'][0]['commerce']['variants'][0]['stock']);
        $this->assertSame(12, $listing['products'][0]['commerce']['price']);
        $this->assertNull($listing['products'][0]['commerce']['compareAtPrice']);
        $section['sourceId'] = '9999';
        $invalid = app(RuntimeAdapterRegistry::class)->factsFor(['sections' => [$section]]);
        $this->assertSame([], $invalid['sections'][0]['listing']['products']);
        $this->assertFalse($invalid['sections'][0]['listing']['sourceId']['valid']);
    }
}
