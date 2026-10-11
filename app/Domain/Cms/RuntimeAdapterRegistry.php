<?php

namespace App\Domain\Cms;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;

final class RuntimeAdapterRegistry
{
    /**
     * Resolve runtime facts without changing the author-owned document.
     *
     * @param  array<string, mixed>  $document
     * @return array{
     *     sections: array<int, array<string, mixed>>,
     *     catalog: array{products: list<array<string, mixed>>, categories: list<array<string, mixed>>, collections: list<array<string, mixed>>}
     * }
     */
    public function factsFor(array $document): array
    {
        $references = $this->collectReferences($document);
        $categories = $this->activeCategories($references['categoryIds']);
        $collections = $this->activeCollections($references['collectionIds']);
        // Navigation references need taxonomy only, never its entire product catalog.
        $commerceReferences = $this->collectReferences(['sections' => array_values(array_filter($document['sections'] ?? [], fn ($s) => ($s['type'] ?? null) !== 'shop_navigation'))]);
        $allLimit = 0;
        foreach ($document['sections'] ?? [] as $section) {
            if (($section['type'] ?? null) !== 'product_listing' || !in_array($section['listingPreset'] ?? null, ['editorial-tabs','pantry-tabs','tech-tabs','kids-tabs'], true) || !($section['isVisible'] ?? false)) continue;
            foreach ($section['productTabs'] ?? [] as $tab) if (($tab['sourceType'] ?? null) === 'all') $allLimit = max($allLimit, min(12, $tab['productLimit'] ?? $section['productLimit'] ?? 4));
        }
        if ($allLimit > 0) $commerceReferences['productIds'] = array_values(array_unique(array_merge($commerceReferences['productIds'], Product::query()->where('status','active')->orderBy('id')->limit($allLimit)->pluck('id')->all())));
        $products = $this->activeProducts(
            $commerceReferences['productIds'],
            array_values(array_intersect($categories->keys()->all(), $commerceReferences['categoryIds'])),
            array_values(array_intersect($collections->keys()->all(), $commerceReferences['collectionIds'])),
        );
        $coupons = $this->validCoupons($references['couponIds']);
        if ($products->isNotEmpty() && collect($document['sections'] ?? [])->contains(fn ($s) => ($s['type'] ?? null) === 'product_listing' && ($s['isVisible'] ?? false))) {
            $products->load(['variants' => fn ($query) => $query->orderBy('id')]);
        }
        $sectionFacts = [];
        $deliveryRows = null;

        foreach ($document['sections'] ?? [] as $sectionIndex => $section) {
            if (! is_array($section) || ($section['isVisible'] ?? false) !== true) {
                continue;
            }

            $facts = match ($section['type'] ?? null) {
                'chapter_block' => ($section['chapterPreset'] ?? '') === 'delivery' ? ['delivery' => ['rows' => $deliveryRows ??= app(\App\Services\DeliveryInformationService::class)->rows()]] : [],
                'promotion_highlights' => ['promotions' => ['offers' => app(\App\Services\PromotionHighlightsService::class)->offers($section)]],
                'payment_methods' => ['payments' => app(\App\Services\PaymentInformationService::class)->facts(app(\App\Support\StorefrontCurrencyContext::class)->current()['code'])],
                'delivery_table' => ['delivery' => ['rows' => $deliveryRows ??= app(\App\Services\DeliveryInformationService::class)->rows()]],
                'product_grid' => $this->productGridFacts($section, $products),
                'product_listing' => $this->productListingFacts($section, $categories, $collections, $products),
                'collection_tabs' => $this->collectionTabFacts($section, $categories, $collections, $products),
                'newsletter_signup' => $this->newsletterFacts($section, $coupons),
                default => [],
            };

            if ($facts !== []) {
                $sectionFacts[$sectionIndex] = $facts;
            }
        }

        return [
            'sections' => $sectionFacts,
            'catalog' => [
                'products' => $products->values()->map($this->projectProduct(...))->all(),
                'categories' => $categories->values()->map($this->projectCategory(...))->all(),
                'collections' => $collections->values()->map($this->projectCollection(...))->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{productIds: list<int>, categoryIds: list<int>, collectionIds: list<int>, couponIds: list<int>}
     */
    private function collectReferences(array $document): array
    {
        $references = [
            'productIds' => [],
            'categoryIds' => [],
            'collectionIds' => [],
            'couponIds' => [],
        ];

        foreach ($document['sections'] ?? [] as $section) {
            if (! is_array($section) || ($section['isVisible'] ?? false) !== true) {
                continue;
            }

            if (($section['type'] ?? null) === 'shop_navigation') {
                foreach ($section['items'] ?? [] as $item) {
                    $id = $this->canonicalReferenceId($item['sourceId'] ?? null);
                    if ($id !== null && in_array($item['sourceType'] ?? null, ['category', 'collection'], true)) {
                        $references[$item['sourceType'] === 'category' ? 'categoryIds' : 'collectionIds'][] = $id;
                    }
                }
            }

            if (($section['type'] ?? null) === 'product_grid' || (($section['type'] ?? null) === 'product_listing' && ($section['sourceType'] ?? null) === 'manual')) {
                foreach ($section['productIds'] ?? [] as $productId) {
                    $id = $this->canonicalReferenceId($productId);

                    if ($id !== null) {
                        $references['productIds'][] = $id;
                    }
                }
            }

            if (in_array($section['type'] ?? null, ['collection_tabs', 'product_listing'], true)) {
                foreach (($section['type'] === 'product_listing' ? (in_array($section['listingPreset'] ?? null, ['editorial-tabs','pantry-tabs','tech-tabs','kids-tabs'], true) ? ($section['productTabs'] ?? []) : [$section]) : ($section['tabs'] ?? [])) as $tab) {
                    if (! is_array($tab)) {
                        continue;
                    }

                    if (($tab['sourceType'] ?? null) === 'manual') foreach ($tab['productIds'] ?? [] as $productId) {
                        $productId = $this->canonicalReferenceId($productId);
                        if ($productId !== null) $references['productIds'][] = $productId;
                    }
                    $id = $this->canonicalReferenceId($tab['sourceId'] ?? null);

                    if ($id === null) {
                        continue;
                    }

                    if (($tab['sourceType'] ?? null) === 'category') {
                        $references['categoryIds'][] = $id;
                    }

                    if (($tab['sourceType'] ?? null) === 'collection') {
                        $references['collectionIds'][] = $id;
                    }
                }
            }

            if (($section['type'] ?? null) === 'newsletter_signup') {
                $id = $this->canonicalReferenceId($section['newsletterCouponId'] ?? null);

                if ($id !== null) {
                    $references['couponIds'][] = $id;
                }
            }
        }

        foreach ($references as &$ids) {
            $ids = array_values(array_unique($ids));
        }
        unset($ids);

        return $references;
    }

    /** @param list<string|int> $ids @return SupportCollection<int, Category> */
    private function activeCategories(array $ids): SupportCollection
    {
        $ids = $this->databaseIds($ids);

        if ($ids === []) {
            return collect();
        }

        return Category::query()
            ->whereKey($ids)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'image_url'])
            ->keyBy('id');
    }

    /** @param list<string|int> $ids @return SupportCollection<int, Collection> */
    private function activeCollections(array $ids): SupportCollection
    {
        $ids = $this->databaseIds($ids);

        if ($ids === []) {
            return collect();
        }

        return Collection::query()
            ->whereKey($ids)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'cover_image'])
            ->keyBy('id');
    }

    /**
     * @param  list<string|int>  $explicitIds
     * @param  list<string|int>  $categoryIds
     * @param  list<string|int>  $collectionIds
     * @return SupportCollection<int, Product>
     */
    private function activeProducts(array $explicitIds, array $categoryIds, array $collectionIds): SupportCollection
    {
        $explicitIds = $this->databaseIds($explicitIds);
        $categoryIds = $this->databaseIds($categoryIds);
        $collectionIds = $this->databaseIds($collectionIds);

        if ($explicitIds === [] && $categoryIds === [] && $collectionIds === []) {
            return collect();
        }

        $relations = [
            'images' => fn ($query) => $query
                ->select(['id', 'product_id', 'path', 'position'])
                ->orderBy('position')
                ->orderBy('id'),
        ];

        if ($collectionIds !== []) {
            $relations['collections'] = fn ($query) => $query
                ->whereIn('collections.id', $collectionIds)
                ->select(['collections.id']);
        }

        return Product::query()
            ->where('status', 'active')
            ->where(function (Builder $query) use ($explicitIds, $categoryIds, $collectionIds): void {
                if ($explicitIds !== []) {
                    $query->whereKey($explicitIds);
                }

                if ($categoryIds !== []) {
                    $method = $explicitIds === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('category_id', $categoryIds);
                }

                if ($collectionIds !== []) {
                    $method = $explicitIds === [] && $categoryIds === [] ? 'whereHas' : 'orWhereHas';
                    $query->{$method}('collections', fn (Builder $collectionQuery) => $collectionQuery->whereIn('collections.id', $collectionIds));
                }
            })
            ->with($relations)
            ->orderBy('products.id')
            ->get([
                'products.id',
                'products.category_id',
                'products.handle',
                'products.title',
                'products.subtitle',
                'products.brand',
                'products.base_price',
                'products.compare_at_price',
                'products.tags',
                'products.options',
            ])
            ->keyBy('id');
    }

    /** @param list<string|int> $ids @return SupportCollection<int, Coupon> */
    private function validCoupons(array $ids): SupportCollection
    {
        $ids = $this->databaseIds($ids);

        if ($ids === []) {
            return collect();
        }

        return Coupon::query()
            ->currentlyValid()
            ->whereKey($ids)
            ->get(['id'])
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  SupportCollection<int, Product>  $products
     * @return array<string, mixed>
     */
    private function productGridFacts(array $section, SupportCollection $products): array
    {
        $requestedIds = collect($section['productIds'] ?? [])->values();
        $invalidIds = $requestedIds
            ->reject(function (mixed $reference) use ($products): bool {
                $id = $this->canonicalReferenceId($reference);

                return $id !== null && $products->has($id);
            })
            ->all();
        $resolved = $requestedIds
            ->map(function (mixed $reference) use ($products): ?Product {
                $id = $this->canonicalReferenceId($reference);

                return $id === null ? null : $products->get($id);
            })
            ->filter()
            ->map($this->projectProduct(...))
            ->values()
            ->all();

        return [
            'productIds' => [
                'valid' => $invalidIds === [],
                'products' => $resolved,
                'invalidReferenceIds' => array_values($invalidIds),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  SupportCollection<int, Category>  $categories
     * @param  SupportCollection<int, Collection>  $collections
     * @param  SupportCollection<int, Product>  $products
     * @return array<string, mixed>
     */
    private function collectionTabFacts(
        array $section,
        SupportCollection $categories,
        SupportCollection $collections,
        SupportCollection $products,
    ): array {
        $tabFacts = [];

        foreach ($section['tabs'] ?? [] as $tabIndex => $tab) {
            if (! is_array($tab)) {
                continue;
            }

            $sourceId = $this->canonicalReferenceId($tab['sourceId'] ?? null);
            $sourceType = $tab['sourceType'] ?? null;
            $source = $sourceId !== null && $sourceType === 'category'
                ? $categories->get($sourceId)
                : ($sourceId !== null && $sourceType === 'collection' ? $collections->get($sourceId) : null);
            $limit = is_int($tab['productLimit'] ?? null) ? $tab['productLimit'] : 0;
            $tabProducts = collect();

            if ($source instanceof Category) {
                $tabProducts = $products->where('category_id', $source->getKey());
            }

            if ($source instanceof Collection) {
                $tabProducts = $products->filter(
                    fn (Product $product): bool => $product->relationLoaded('collections')
                        && $product->collections->contains('id', $source->getKey()),
                );
            }

            $tabFacts[$tabIndex] = [
                'sourceId' => ['valid' => $source !== null],
                'source' => match (true) {
                    $source instanceof Category => $this->projectCategory($source),
                    $source instanceof Collection => $this->projectCollection($source),
                    default => null,
                },
                'products' => $tabProducts
                    ->take($limit)
                    ->map($this->projectProduct(...))
                    ->values()
                    ->all(),
            ];
        }

        return ['tabs' => $tabFacts];
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  SupportCollection<int, Category>  $categories
     * @param  SupportCollection<int, Collection>  $collections
     * @param  SupportCollection<int, Product>  $products
     * @return array<string, mixed>
     */
    private function productListingFacts(array $section, SupportCollection $categories, SupportCollection $collections, SupportCollection $products): array
    {
        if (in_array($section['listingPreset'] ?? null, ['editorial-tabs','pantry-tabs','tech-tabs','kids-tabs'], true)) {
            $tabs = [];
            foreach ($section['productTabs'] ?? [] as $tab) {
                $feed = array_merge($section,$tab,['listingPreset'=>'editorial-grid','productLimit'=>$tab['productLimit'] ?? $section['productLimit'] ?? 4]);
                $tabs[] = $this->productListingFacts($feed,$categories,$collections,$products)['listing'];
            }
            return ['tabs'=>$tabs];
        }
        $section['productLimit'] ??= 6;
        if (($section['sourceType'] ?? null) === 'all') {
            $facts = ['sourceId'=>['valid'=>true], 'source'=>null, 'products'=>$products->sortBy('id')->take($section['productLimit'])->map($this->projectProduct(...))->values()->all()];
        } elseif (($section['sourceType'] ?? null) === 'manual') {
            $requested = array_values(array_unique($section['productIds'] ?? []));
            $selected = collect($requested)->map(fn ($id) => $this->canonicalReferenceId($id))->map(fn ($id) => $id === null ? null : $products->get($id));
            $facts = ['sourceId'=>['valid'=>$requested !== [] && $selected->filter()->count() === count($requested)], 'source'=>null,
                'products'=>$selected->filter()->take($section['productLimit'])->map(fn ($product) => $this->projectProduct($product))->values()->all()];
        } else {
            $facts = $this->collectionTabFacts(['tabs' => [$section]], $categories, $collections, $products)['tabs'][0];
        }
        $facts['products'] = array_map(function (array $item) use ($products): array {
            $product = $products->get((int) $item['id']);
            $variant = $product->variants->first();
            $item['commerce'] = [
                'variantId' => $variant ? (string) $variant->id : null,
                'sku' => (string) ($variant?->sku ?? ''),
                'price' => ($variant?->price ?? $product->base_price) / 100,
                'compareAtPrice' => $variant?->compare_at_price !== null ? $variant->compare_at_price / 100 : null,
                'optionLabels' => array_values(array_map('strval', data_get($product->options, '0.values', []))),
                'canChoose' => $variant !== null,
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => (string) $v->id,
                    'label' => (string) ($v->color_name ?: $v->title),
                    'color' => preg_match('/^#[0-9a-f]{6}$/i',(string)($v->color_code ?: $v->color_hex)) ? ($v->color_code ?: $v->color_hex) : null,
                    'sku' => (string) $v->sku,
                    'price' => $v->price / 100,
                    'compareAtPrice' => $v->compare_at_price === null ? null : $v->compare_at_price / 100,
                    'stock' => (int) $v->stock,
                    'enabled' => $v->enabled !== false,
                    'image' => $v->option_image ?: $v->image_url ?: $product->images->firstWhere('product_variant_id', $v->id)?->path,
                ])->values()->all(),
            ];
            return $item;
        }, $facts['products']);
        return ['listing' => $facts];
    }

    /** @return array<string, mixed> */
    private function newsletterFacts(array $section, SupportCollection $coupons): array
    {
        $couponReference = $section['newsletterCouponId'] ?? null;
        $hasCouponReference = $couponReference !== null && $couponReference !== '';
        $couponId = $this->canonicalReferenceId($couponReference);
        $coupon = $couponId === null ? null : $coupons->get($couponId);

        return [
            'newsletterCouponId' => [
                'valid' => ! $hasCouponReference || $coupon !== null,
                'coupon' => $coupon ? ['id' => (string) $coupon->getKey()] : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function projectProduct(Product $product): array
    {
        return [
            'id' => (string) $product->getKey(),
            'title' => (string) $product->title,
            'slug' => (string) $product->handle,
            'brand' => (string) ($product->brand ?? ''),
            'summary' => (string) ($product->subtitle ?? ''),
            'images' => $product->images->pluck('path')->values()->all(),
            'tags' => $product->tags ?? [],
            'basePrice' => $product->base_price / 100,
            'baseCompareAtPrice' => $product->compare_at_price !== null
                ? $product->compare_at_price / 100
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function projectCategory(Category $category): array
    {
        return [
            'id' => (string) $category->getKey(),
            'type' => 'category',
            'slug' => (string) $category->slug,
            'label' => (string) $category->name,
            'image' => $category->image_url,
        ];
    }

    /** @return array<string, mixed> */
    private function projectCollection(Collection $collection): array
    {
        return [
            'id' => (string) $collection->getKey(),
            'type' => 'collection',
            'slug' => (string) $collection->slug,
            'label' => (string) $collection->title,
            'image' => $collection->cover_image,
        ];
    }

    /** @param list<string|int> $ids @return list<int> */
    private function databaseIds(array $ids): array
    {
        return collect($ids)
            ->map($this->canonicalReferenceId(...))
            ->filter(fn (?int $id): bool => $id !== null)
            ->unique()
            ->values()
            ->all();
    }

    private function canonicalReferenceId(mixed $reference): ?int
    {
        if (is_int($reference)) {
            return $reference > 0 ? $reference : null;
        }

        if (! is_string($reference)
            || preg_match('/\A[1-9][0-9]*\z/D', $reference) !== 1) {
            return null;
        }

        $id = filter_var($reference, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return is_int($id) && (string) $id === $reference ? $id : null;
    }
}
