<?php

namespace Tests\Feature\Cms;

use App\Domain\Cms\Exceptions\CmsRuntimeReferenceInvalid;
use App\Domain\Cms\PageCrudService;
use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Domain\Cms\RuntimeAdapterRegistry;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class PageRuntimePayloadTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const MALFORMED_REFERENCE_IDS = [
        '1junk',
        ' 1',
        '1 ',
        '+1',
        '-1',
        '1.0',
        '01',
        '00',
        '0',
        '',
        '999999999999999999999999999999',
    ];

    public function test_product_grid_projects_only_requested_active_products_in_section_order(): void
    {
        $first = $this->product('first-product', status: 'active', attributes: [
            'title' => 'First Product',
            'brand' => 'JW',
            'subtitle' => 'First summary',
            'base_price' => 15999,
            'compare_at_price' => 19999,
            'tags' => ['Best Seller'],
        ]);
        $first->images()->create(['path' => '/images/catalog/first.jpg', 'alt' => 'Internal alt', 'position' => 0]);

        $second = $this->product('second-product', status: 'active', attributes: [
            'title' => 'Second Product',
            'base_price' => 9900,
        ]);
        $inactive = $this->product('inactive-product', status: 'draft');

        $document = $this->document([
            $this->productGrid([(string) $second->id, (string) $inactive->id, (string) $first->id]),
        ]);
        $authorDocument = $document;

        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);
        $products = $facts['sections'][0]['productIds']['products'];

        $this->assertSame($authorDocument, $document);
        $this->assertSame([(string) $second->id, (string) $first->id], array_column($products, 'id'));
        $this->assertFalse($facts['sections'][0]['productIds']['valid']);
        $this->assertSame([(string) $inactive->id], $facts['sections'][0]['productIds']['invalidReferenceIds']);
        $this->assertSame([
            'id',
            'title',
            'slug',
            'brand',
            'summary',
            'images',
            'tags',
            'basePrice',
            'baseCompareAtPrice',
        ], array_keys($products[1]));
        $this->assertSame([
            'id' => (string) $first->id,
            'title' => 'First Product',
            'slug' => 'first-product',
            'brand' => 'JW',
            'summary' => 'First summary',
            'images' => ['/images/catalog/first.jpg'],
            'tags' => ['Best Seller'],
            'basePrice' => 159.99,
            'baseCompareAtPrice' => 199.99,
        ], $products[1]);
    }

    public function test_invalid_product_reference_remains_readable_in_draft_and_blocks_publication_with_a_stable_interface(): void
    {
        $inactive = $this->product('draft-only-product', status: 'draft');
        $document = $this->document([
            $this->productGrid([(string) $inactive->id, '999999']),
        ], slug: 'invalid-product-draft');

        $page = app(PageCrudService::class)->create($document);
        $payload = app(PageRuntimePayloadFactory::class)->forDraft($page);

        $this->assertSame($document, $payload['page']['document']);
        $this->assertSame([
            'The runtime reference for this visible section is invalid.',
        ], $payload['runtime']['errors']['sections.0.productIds']);

        try {
            app(PageDocumentContract::class)->assertPublishable(
                $document,
                app(RuntimeAdapterRegistry::class)->factsFor($document),
            );
            $this->fail('Expected invalid runtime references to block publication.');
        } catch (CmsRuntimeReferenceInvalid $exception) {
            $this->assertSame('cms_runtime_reference_invalid', $exception->machineCode());
            $this->assertSame([
                'The runtime reference for this visible section is invalid.',
            ], $exception->errors()['sections.0.productIds']);
        }
    }

    public function test_malformed_product_ids_cannot_piggyback_on_a_valid_product_loaded_by_another_section(): void
    {
        $product = $this->product('canonical-product-one');
        $this->assertSame(1, $product->id);
        $document = $this->document([
            $this->productGrid(['1'], 'valid-product-grid'),
            $this->productGrid(self::MALFORMED_REFERENCE_IDS, 'malformed-product-grid'),
        ]);
        $authorDocument = $document;

        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);
        $malformedFact = $facts['sections'][1]['productIds'];

        $this->assertSame($authorDocument, $document);
        $this->assertTrue($facts['sections'][0]['productIds']['valid']);
        $this->assertSame(['1'], array_column($facts['sections'][0]['productIds']['products'], 'id'));
        $this->assertFalse($malformedFact['valid']);
        $this->assertSame([], $malformedFact['products']);
        $this->assertSame(self::MALFORMED_REFERENCE_IDS, $malformedFact['invalidReferenceIds']);

        $errors = $this->publicationErrors($document, $facts);
        $this->assertArrayNotHasKey('sections.0.productIds', $errors);
        $this->assertSame([
            'The runtime reference for this visible section is invalid.',
        ], $errors['sections.1.productIds']);
    }

    public function test_malformed_category_and_collection_ids_cannot_piggyback_on_valid_sources_loaded_by_other_tabs(): void
    {
        $category = Category::query()->create([
            'slug' => 'canonical-category-one',
            'name' => 'Canonical Category One',
            'is_active' => true,
        ]);
        $collection = Collection::query()->create([
            'slug' => 'canonical-collection-one',
            'title' => 'Canonical Collection One',
            'type' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ]);
        $product = $this->product('source-membership-product', category: $category);
        $collection->products()->attach($product);

        $this->assertSame(1, $category->id);
        $this->assertSame(1, $collection->id);

        $tabs = [
            $this->runtimeTab('valid-category', 'category', '1'),
            $this->runtimeTab('valid-collection', 'collection', '1'),
        ];

        foreach (self::MALFORMED_REFERENCE_IDS as $index => $sourceId) {
            $tabs[] = $this->runtimeTab("malformed-category-{$index}", 'category', $sourceId);
            $tabs[] = $this->runtimeTab("malformed-collection-{$index}", 'collection', $sourceId);
        }

        $document = $this->document([$this->collectionTabs($tabs)]);
        $authorDocument = $document;
        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);
        $tabFacts = $facts['sections'][0]['tabs'];

        $this->assertSame($authorDocument, $document);
        $this->assertTrue($tabFacts[0]['sourceId']['valid']);
        $this->assertTrue($tabFacts[1]['sourceId']['valid']);

        foreach (array_keys(self::MALFORMED_REFERENCE_IDS) as $index) {
            $categoryIndex = 2 + ($index * 2);
            $collectionIndex = $categoryIndex + 1;

            $this->assertFalse($tabFacts[$categoryIndex]['sourceId']['valid']);
            $this->assertNull($tabFacts[$categoryIndex]['source']);
            $this->assertSame([], $tabFacts[$categoryIndex]['products']);
            $this->assertFalse($tabFacts[$collectionIndex]['sourceId']['valid']);
            $this->assertNull($tabFacts[$collectionIndex]['source']);
            $this->assertSame([], $tabFacts[$collectionIndex]['products']);
        }

        $errors = $this->publicationErrors($document, $facts);
        $this->assertArrayNotHasKey('sections.0.tabs.0.sourceId', $errors);
        $this->assertArrayNotHasKey('sections.0.tabs.1.sourceId', $errors);

        foreach (array_keys(self::MALFORMED_REFERENCE_IDS) as $index) {
            $categoryIndex = 2 + ($index * 2);
            $collectionIndex = $categoryIndex + 1;
            $this->assertArrayHasKey("sections.0.tabs.{$categoryIndex}.sourceId", $errors);
            $this->assertArrayHasKey("sections.0.tabs.{$collectionIndex}.sourceId", $errors);
        }
    }

    public function test_malformed_coupon_ids_cannot_piggyback_on_a_valid_coupon_loaded_by_another_section(): void
    {
        $coupon = $this->coupon('CANONICAL1');
        $this->assertSame(1, $coupon->id);
        $sections = [$this->newsletter('1', 'valid-newsletter')];

        foreach (self::MALFORMED_REFERENCE_IDS as $index => $couponId) {
            $sections[] = $this->newsletter($couponId, "malformed-newsletter-{$index}");
        }

        $document = $this->document($sections);
        $authorDocument = $document;
        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);

        $this->assertSame($authorDocument, $document);
        $this->assertTrue($facts['sections'][0]['newsletterCouponId']['valid']);
        $this->assertSame(['id' => '1'], $facts['sections'][0]['newsletterCouponId']['coupon']);

        foreach (self::MALFORMED_REFERENCE_IDS as $index => $couponId) {
            $sectionIndex = $index + 1;
            $fact = $facts['sections'][$sectionIndex]['newsletterCouponId'];

            $this->assertNull($fact['coupon']);
            $this->assertSame($couponId === '', $fact['valid']);
        }

        $errors = $this->publicationErrors($document, $facts);
        $this->assertArrayNotHasKey('sections.0.newsletterCouponId', $errors);

        foreach (self::MALFORMED_REFERENCE_IDS as $index => $couponId) {
            $path = 'sections.'.($index + 1).'.newsletterCouponId';

            if ($couponId === '') {
                $this->assertArrayNotHasKey($path, $errors);
            } else {
                $this->assertArrayHasKey($path, $errors);
            }
        }
    }

    public function test_collection_tabs_resolve_active_category_and_collection_products_with_each_tab_limit(): void
    {
        $category = Category::query()->create([
            'slug' => 'headphones',
            'name' => 'Headphones',
            'image_url' => '/images/catalog/headphones.jpg',
            'is_active' => true,
        ]);
        $collection = Collection::query()->create([
            'slug' => 'studio-picks',
            'title' => 'Studio Picks',
            'cover_image' => '/images/catalog/studio-picks.jpg',
            'type' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ]);

        $categoryProducts = collect(range(1, 4))->map(fn (int $index): Product => $this->product(
            "category-product-{$index}",
            category: $category,
        ));
        $inactiveCategoryProduct = $this->product('inactive-category-product', status: 'draft', category: $category);
        $collectionProducts = collect(range(1, 5))->map(fn (int $index): Product => $this->product("collection-product-{$index}"));
        $collection->products()->attach($collectionProducts->push($inactiveCategoryProduct)->pluck('id'));

        $document = $this->document([
            $this->collectionTabs([
                [
                    'id' => 'category-tab',
                    'label' => 'By Category',
                    'sourceType' => 'category',
                    'sourceId' => (string) $category->id,
                    'productLimit' => 3,
                ],
                [
                    'id' => 'collection-tab',
                    'label' => 'By Collection',
                    'sourceType' => 'collection',
                    'sourceId' => (string) $collection->id,
                    'productLimit' => 4,
                ],
            ]),
        ]);

        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);
        $categoryFact = $facts['sections'][0]['tabs'][0];
        $collectionFact = $facts['sections'][0]['tabs'][1];

        $this->assertTrue($categoryFact['sourceId']['valid']);
        $this->assertSame([
            'id' => (string) $category->id,
            'type' => 'category',
            'slug' => 'headphones',
            'label' => 'Headphones',
            'image' => '/images/catalog/headphones.jpg',
        ], $categoryFact['source']);
        $this->assertSame(
            $categoryProducts->take(3)->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            array_column($categoryFact['products'], 'id'),
        );

        $this->assertTrue($collectionFact['sourceId']['valid']);
        $this->assertSame([
            'id' => (string) $collection->id,
            'type' => 'collection',
            'slug' => 'studio-picks',
            'label' => 'Studio Picks',
            'image' => '/images/catalog/studio-picks.jpg',
        ], $collectionFact['source']);
        $this->assertSame(
            $collectionProducts->take(4)->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            array_column($collectionFact['products'], 'id'),
        );

        app(PageDocumentContract::class)->assertPublishable($document, $facts);
        $this->assertTrue(true);
    }

    public function test_collection_tab_invalid_source_uses_the_exact_nested_publication_path(): void
    {
        $inactive = Category::query()->create([
            'slug' => 'hidden-category',
            'name' => 'Hidden Category',
            'is_active' => false,
        ]);
        $document = $this->document([
            $this->collectionTabs([
                [
                    'id' => 'missing-tab',
                    'label' => 'Missing',
                    'sourceType' => 'collection',
                    'sourceId' => '999999',
                    'productLimit' => 3,
                ],
                [
                    'id' => 'inactive-tab',
                    'label' => 'Inactive',
                    'sourceType' => 'category',
                    'sourceId' => (string) $inactive->id,
                    'productLimit' => 4,
                ],
            ]),
        ]);
        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);

        try {
            app(PageDocumentContract::class)->assertPublishable($document, $facts);
            $this->fail('Expected invalid tab sources to block publication.');
        } catch (CmsRuntimeReferenceInvalid $exception) {
            $this->assertArrayNotHasKey('sections.0.type', $exception->errors());
            $this->assertSame([
                'sections.0.tabs.0.sourceId',
                'sections.0.tabs.1.sourceId',
            ], array_keys($exception->errors()));
            $this->assertSame([
                'The runtime reference for this visible section is invalid.',
            ], $exception->errors()['sections.0.tabs.0.sourceId']);
            $this->assertSame([
                'The runtime reference for this visible section is invalid.',
            ], $exception->errors()['sections.0.tabs.1.sourceId']);
        }
    }

    public function test_newsletter_exposes_only_coupon_identity_and_rejects_inactive_or_exhausted_coupons(): void
    {
        $valid = $this->coupon('VALID30');
        $inactive = $this->coupon('INACTIVE30', ['is_active' => false]);
        $exhausted = $this->coupon('EXHAUSTED30', ['usage_limit' => 5, 'usage_count' => 5]);
        $document = $this->document([
            $this->newsletter((string) $valid->id, 'valid-newsletter'),
            $this->newsletter((string) $inactive->id, 'inactive-newsletter'),
            $this->newsletter((string) $exhausted->id, 'exhausted-newsletter'),
        ]);

        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);

        $this->assertTrue($facts['sections'][0]['newsletterCouponId']['valid']);
        $this->assertSame(['id' => (string) $valid->id], $facts['sections'][0]['newsletterCouponId']['coupon']);
        $this->assertFalse($facts['sections'][1]['newsletterCouponId']['valid']);
        $this->assertNull($facts['sections'][1]['newsletterCouponId']['coupon']);
        $this->assertFalse($facts['sections'][2]['newsletterCouponId']['valid']);
        $this->assertNull($facts['sections'][2]['newsletterCouponId']['coupon']);
        $this->assertArrayNotHasKey('code', $facts['sections'][0]['newsletterCouponId']['coupon']);
        $this->assertArrayNotHasKey('usageLimit', $facts['sections'][0]['newsletterCouponId']['coupon']);

        try {
            app(PageDocumentContract::class)->assertPublishable($document, $facts);
            $this->fail('Expected invalid newsletter coupons to block publication.');
        } catch (CmsRuntimeReferenceInvalid $exception) {
            $this->assertSame('cms_runtime_reference_invalid', $exception->machineCode());
            $this->assertArrayNotHasKey('sections.0.newsletterCouponId', $exception->errors());
            $this->assertArrayHasKey('sections.1.newsletterCouponId', $exception->errors());
            $this->assertArrayHasKey('sections.2.newsletterCouponId', $exception->errors());
        }
    }

    public function test_hidden_invalid_sections_neither_resolve_nor_block_publication(): void
    {
        $document = $this->document([
            array_merge($this->productGrid(['999999']), ['isVisible' => false]),
            array_merge($this->newsletter('999999'), ['isVisible' => false]),
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $facts = app(RuntimeAdapterRegistry::class)->factsFor($document);
        $queryCount = $this->selectQueryCount();

        $this->assertSame([], $facts['sections']);
        $this->assertSame(0, $queryCount);
        $this->assertSame([], app(PageDocumentContract::class)->runtimeErrors($document, $facts));
        app(PageDocumentContract::class)->assertPublishable($document, $facts);
    }

    public function test_preview_and_public_payloads_share_shape_but_read_their_own_canonical_document_stage(): void
    {
        $draft = $this->document([], title: 'Draft Title', slug: 'runtime-stage');
        $published = $this->document([], title: 'Published Title', slug: 'runtime-stage');
        $page = Page::query()->create(['status' => 'published', 'published_at' => now()]);
        $this->storeDocument($page, 'draft', $draft, lockVersion: 2);
        $this->storeDocument($page, 'published', $published, lockVersion: 1);
        $factory = app(PageRuntimePayloadFactory::class);

        $preview = $factory->forDraft($page->fresh());
        $public = $factory->forCanary($page->fresh());

        $this->assertSame(array_keys($preview), array_keys($public));
        $this->assertSame(array_keys($preview['page']), array_keys($public['page']));
        $this->assertSame(array_keys($preview['context']), array_keys($public['context']));
        $this->assertSame('Draft Title', $preview['page']['document']['title']);
        $this->assertSame('Published Title', $public['page']['document']['title']);
        $this->assertSame('draft', $preview['page']['stage']);
        $this->assertSame('published', $public['page']['stage']);
        $this->assertSame('preview', $preview['context']['mode']);
        $this->assertSame('public', $public['context']['mode']);
        $this->assertSame(PageDocumentHasher::hash($draft), $preview['page']['contentHash']);
        $this->assertSame(PageDocumentHasher::hash($published), $public['page']['contentHash']);
    }

    public function test_published_payload_requires_published_status_and_a_published_snapshot(): void
    {
        $page = Page::query()->create(['status' => 'draft']);
        $this->storeDocument($page, 'published', $this->document([], slug: 'guarded-public'));

        try {
            app(PageRuntimePayloadFactory::class)->forCanary($page->fresh());
            $this->fail('Expected a draft page to be unavailable publicly.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $pageWithoutSnapshot = Page::query()->create(['status' => 'published']);

        $this->expectException(NotFoundHttpException::class);
        app(PageRuntimePayloadFactory::class)->forCanary($pageWithoutSnapshot);
    }

    public function test_draft_payload_requires_a_draft_snapshot(): void
    {
        $page = Page::query()->create(['status' => 'draft']);

        $this->expectException(NotFoundHttpException::class);
        app(PageRuntimePayloadFactory::class)->forDraft($page);
    }

    public function test_product_runtime_query_count_does_not_grow_per_card_or_section(): void
    {
        $products = collect(range(1, 12))->map(fn (int $index): Product => $this->product("bounded-product-{$index}"));
        $registry = app(RuntimeAdapterRegistry::class);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $registry->factsFor($this->document([
            $this->productGrid([(string) $products->first()->id]),
        ]));
        $singleQueryCount = $this->selectQueryCount();

        DB::flushQueryLog();
        $registry->factsFor($this->document([
            $this->productGrid($products->take(6)->pluck('id')->map(fn ($id): string => (string) $id)->all(), 'grid-one'),
            $this->productGrid($products->skip(6)->pluck('id')->map(fn ($id): string => (string) $id)->all(), 'grid-two'),
        ]));
        $manyQueryCount = $this->selectQueryCount();

        $this->assertSame(2, $singleQueryCount);
        $this->assertSame(2, $manyQueryCount);
        $this->assertSame($singleQueryCount, $manyQueryCount);
        $this->assertLessThanOrEqual(2, $manyQueryCount);
    }

    public function test_mixed_runtime_query_categories_remain_fixed_as_visible_references_scale_and_hidden_references_are_excluded(): void
    {
        $categories = collect(range(1, 4))->map(fn (int $index): Category => Category::query()->create([
            'slug' => "bounded-category-{$index}",
            'name' => "Bounded Category {$index}",
            'is_active' => true,
        ]));
        $collections = collect(range(1, 4))->map(fn (int $index): Collection => Collection::query()->create([
            'slug' => "bounded-collection-{$index}",
            'title' => "Bounded Collection {$index}",
            'type' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ]));
        $products = $categories->flatMap(function (Category $category, int $categoryIndex): array {
            return [
                $this->product("mixed-product-{$categoryIndex}-one", category: $category),
                $this->product("mixed-product-{$categoryIndex}-two", category: $category),
            ];
        })->values();

        foreach ($collections as $index => $collection) {
            $collection->products()->attach($products->slice($index, 2)->pluck('id'));
        }

        $coupons = collect(range(1, 4))->map(fn (int $index): Coupon => $this->coupon("BOUNDED{$index}"));
        $hiddenCategory = Category::query()->create([
            'slug' => 'hidden-query-category',
            'name' => 'Hidden Query Category',
            'is_active' => true,
        ]);
        $hiddenCollection = Collection::query()->create([
            'slug' => 'hidden-query-collection',
            'title' => 'Hidden Query Collection',
            'type' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ]);
        $hiddenProduct = $this->product('hidden-query-product', category: $hiddenCategory);
        $hiddenCollection->products()->attach($hiddenProduct);
        $hiddenCoupon = $this->coupon('HIDDENQUERY');

        $small = $this->document([
            $this->productGrid([(string) $products[0]->id], 'small-grid'),
            $this->collectionTabs([
                $this->runtimeTab('small-category', 'category', (string) $categories[0]->id),
                $this->runtimeTab('small-collection', 'collection', (string) $collections[0]->id),
            ], 'small-tabs'),
            $this->newsletter((string) $coupons[0]->id, 'small-newsletter'),
        ]);
        $largeSections = [];

        foreach ($products->chunk(2) as $index => $chunk) {
            $largeSections[] = $this->productGrid(
                $chunk->pluck('id')->map(fn ($id): string => (string) $id)->all(),
                "large-grid-{$index}",
            );
        }

        $largeTabs = [];
        foreach ($categories as $index => $category) {
            $largeTabs[] = $this->runtimeTab("large-category-{$index}", 'category', (string) $category->id);
        }
        foreach ($collections as $index => $collection) {
            $largeTabs[] = $this->runtimeTab("large-collection-{$index}", 'collection', (string) $collection->id);
        }
        $largeSections[] = $this->collectionTabs($largeTabs, 'large-tabs');
        foreach ($coupons as $index => $coupon) {
            $largeSections[] = $this->newsletter((string) $coupon->id, "large-newsletter-{$index}");
        }
        $large = $this->document($largeSections);
        $largeWithHidden = $large;
        $largeWithHidden['sections'][] = array_merge(
            $this->productGrid([(string) $hiddenProduct->id], 'hidden-grid'),
            ['isVisible' => false],
        );
        $largeWithHidden['sections'][] = array_merge($this->collectionTabs([
            $this->runtimeTab('hidden-category', 'category', (string) $hiddenCategory->id),
            $this->runtimeTab('hidden-collection', 'collection', (string) $hiddenCollection->id),
        ], 'hidden-tabs'), ['isVisible' => false]);
        $largeWithHidden['sections'][] = array_merge(
            $this->newsletter((string) $hiddenCoupon->id, 'hidden-newsletter'),
            ['isVisible' => false],
        );

        $registry = app(RuntimeAdapterRegistry::class);
        [$smallFacts, $smallCategories] = $this->runtimeFactsAndSelectCategories($registry, $small);
        [$largeFacts, $largeCategories] = $this->runtimeFactsAndSelectCategories($registry, $large);
        [$hiddenFacts, $hiddenCategories] = $this->runtimeFactsAndSelectCategories($registry, $largeWithHidden);
        $expected = [
            'categories',
            'collections',
            'products',
            'product_images',
            'collection_membership',
            'coupons',
        ];

        $this->assertSame($expected, $smallCategories);
        $this->assertSame($expected, $largeCategories);
        $this->assertSame($expected, $hiddenCategories);
        $this->assertCount(6, $smallCategories);
        $this->assertCount(6, $largeCategories);
        $this->assertCount(6, $hiddenCategories);
        $this->assertCount(count($largeFacts['sections']), $hiddenFacts['sections']);
        $this->assertNotContains((string) $hiddenProduct->id, array_column($hiddenFacts['catalog']['products'], 'id'));
        $this->assertNotContains((string) $hiddenCategory->id, array_column($hiddenFacts['catalog']['categories'], 'id'));
        $this->assertNotContains((string) $hiddenCollection->id, array_column($hiddenFacts['catalog']['collections'], 'id'));
        $this->assertSame($smallFacts['sections'][0]['productIds']['valid'], true);
    }

    /** @param array<string, mixed> $attributes */
    private function product(
        string $handle,
        string $status = 'active',
        ?Category $category = null,
        array $attributes = [],
    ): Product {
        return Product::query()->create(array_merge([
            'category_id' => $category?->id,
            'handle' => $handle,
            'title' => str($handle)->headline()->toString(),
            'base_price' => 10000,
            'status' => $status,
            'tags' => [],
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function coupon(string $code, array $attributes = []): Coupon
    {
        return Coupon::query()->create(array_merge([
            'code' => $code,
            'discount_type' => 'fixed_amount',
            'value' => 3000,
            'min_spend' => 15000,
            'usage_limit' => 100,
            'usage_count' => 0,
            'per_customer_limit' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ], $attributes));
    }

    /** @param list<array<string, mixed>> $sections @return array<string, mixed> */
    private function document(
        array $sections,
        string $title = 'Runtime Page',
        string $slug = 'runtime-page',
    ): array {
        return [
            'title' => $title,
            'slug' => $slug,
            'description' => '',
            'template' => 'landing',
            'heroImage' => null,
            'seoTitle' => '',
            'seoDescription' => '',
            'sections' => $sections,
        ];
    }

    /** @param list<string> $productIds @return array<string, mixed> */
    private function productGrid(array $productIds, string $id = 'product-grid'): array
    {
        return [
            'id' => $id,
            'type' => 'product_grid',
            'title' => 'Products',
            'isVisible' => true,
            'productIds' => $productIds,
        ];
    }

    /** @param list<array<string, mixed>> $tabs @return array<string, mixed> */
    private function collectionTabs(array $tabs, string $id = 'collection-tabs'): array
    {
        return [
            'id' => $id,
            'type' => 'collection_tabs',
            'title' => 'Collections',
            'isVisible' => true,
            'tabs' => $tabs,
        ];
    }

    /** @return array<string, mixed> */
    private function newsletter(string $couponId, string $id = 'newsletter'): array
    {
        return [
            'id' => $id,
            'type' => 'newsletter_signup',
            'title' => 'Newsletter',
            'isVisible' => true,
            'newsletterCouponId' => $couponId,
        ];
    }

    /** @return array<string, mixed> */
    private function runtimeTab(string $id, string $sourceType, string $sourceId): array
    {
        return [
            'id' => $id,
            'label' => str($id)->headline()->toString(),
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
            'productLimit' => 3,
        ];
    }

    /** @param array<string, mixed> $document */
    private function storeDocument(Page $page, string $stage, array $document, int $lockVersion = 1): void
    {
        $page->documents()->create([
            'stage' => $stage,
            'slug' => $document['slug'],
            'schema_version' => PageDocumentContract::VERSION,
            'lock_version' => $lockVersion,
            'content_hash' => PageDocumentHasher::hash($document),
            'document' => $document,
        ]);
    }

    private function selectQueryCount(): int
    {
        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function runtimeFactsAndSelectCategories(RuntimeAdapterRegistry $registry, array $document): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $facts = $registry->factsFor($document);

        return [$facts, collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->map(function (array $query): string {
                $sql = str_replace(['"', '`'], '', strtolower($query['query']));

                return match (true) {
                    str_contains($sql, 'from categories') => 'categories',
                    str_contains($sql, 'from products') => 'products',
                    str_contains($sql, 'from collection_product') => 'collection_membership',
                    str_contains($sql, 'from collections') && str_contains($sql, 'inner join collection_product') => 'collection_membership',
                    str_contains($sql, 'from collections') => 'collections',
                    str_contains($sql, 'from product_images') => 'product_images',
                    str_contains($sql, 'from coupons') => 'coupons',
                    default => 'unexpected:'.$sql,
                };
            })
            ->values()
            ->all()];
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $facts
     * @return array<string, list<string>>
     */
    private function publicationErrors(array $document, array $facts): array
    {
        try {
            app(PageDocumentContract::class)->assertPublishable($document, $facts);
        } catch (CmsRuntimeReferenceInvalid $exception) {
            $this->assertSame('cms_runtime_reference_invalid', $exception->machineCode());

            return $exception->errors();
        }

        $this->fail('Expected invalid runtime references to block publication.');
    }
}
