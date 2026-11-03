<?php

namespace App\Services;

use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/** The only storefront PLP filter authority: enabled variants and published reviews. */
final class CatalogFilterService
{
    public function for(Request $request, Builder|Relation $base, ?string $forcedCategory = null, ?int $perPage = null): CatalogFilterResult
    {
        $theme = $this->resolvedTheme($request, $base, $forcedCategory);
        $wedding = $theme === 'wedding';
        $filters = $this->normalize($request, $base, $forcedCategory, in_array($theme, ['wedding', 'logistics'], true));
        $products = $this->applyFilters(clone $base, $filters)
            ->with([
                'category', 'images',
                'variants' => $filters['variantFiltered']
                    ? fn (HasMany $variants) => $this->applyVariantConstraints($variants, $filters)
                    : fn (HasMany $variants) => $variants,
            ]);
        if ($wedding) $products->orderBy('products.id');
        $products = $products->paginate($perPage ?? ($wedding ? 3 : 12))->withQueryString();

        return new CatalogFilterResult($filters, $products, $this->facets($base, $filters));
    }

    /** @return array{search:string,category:?string,sort:string,layout:string,minPrice:int,maxPrice:int,boundsMinPrice:int,boundsMaxPrice:int,inStock:bool,colors:list<string>,materials:list<string>,minRating:float,variantFiltered:bool} */
    private function normalize(Request $request, Builder|Relation $base, ?string $forcedCategory, bool $buyOnlyPrices = false): array
    {
        $boundsQuery = $this->variantQuery($base)->where('enabled', true);
        if ($buyOnlyPrices) $boundsQuery->whereHas('product', fn (Builder $p) => $p->where('purchase_mode', 'buy'));
        $hasPricedProducts = $buyOnlyPrices ? (clone $boundsQuery)->exists() : true;
        $bounds = $boundsQuery
            ->selectRaw('COALESCE(MIN(price), 0) as minimum_price, COALESCE(MAX(price), 0) as maximum_price')->first();
        $minimum = max(0, (int) ($bounds?->minimum_price ?? 0));
        $maximum = max($minimum, (int) ($bounds?->maximum_price ?? 0));
        $minPrice = min(max($request->integer('min_price', $minimum), $minimum), $maximum);
        $maxPrice = min(max($request->integer('max_price', $maximum), $minimum), $maximum);
        if ($minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        $filters = [
            'buyOnlyPriceFilters' => $buyOnlyPrices, 'hasPricedProducts' => $hasPricedProducts,
            'search' => $request->string('search')->trim()->value(),
            'category' => $forcedCategory ?? $this->activeCategory($request->string('category')->trim()->value(), $base),
            'sort' => in_array($request->string('sort')->value(), ['price_asc', 'price_desc', 'rating', 'newest'], true) ? $request->string('sort')->value() : '',
            'layout' => $request->string('layout')->value() === 'list' ? 'list' : 'grid',
            'minPrice' => $minPrice, 'maxPrice' => $maxPrice,
            'boundsMinPrice' => $minimum, 'boundsMaxPrice' => $maximum,
            // Other filter controls submit the range values too. Keep intent
            // separate so catalog bounds do not become a fake active chip.
            'priceRequested' => $hasPricedProducts && ($request->boolean('price_filter') || $minPrice !== $minimum || $maxPrice !== $maximum),
            'inStock' => $hasPricedProducts && $request->boolean('in_stock'),
            'colors' => $this->availableVariantValues($request, 'color', 'color_name', $base, $buyOnlyPrices),
            'materials' => $this->availableVariantValues($request, 'material', 'material', $base, $buyOnlyPrices),
            'minRating' => $this->normalizedRating($request),
        ];
        $filters['variantFiltered'] = $this->hasVariantConstraint($filters);
        $requestedTags = $request->input('tag', []);
        $requestedTags = is_array($requestedTags) ? $requestedTags : [$requestedTags];
        $requestedTags = array_slice(array_values(array_unique(array_filter($requestedTags, fn ($value) => is_string($value) && $value !== '' && mb_strlen($value) <= 100))), 0, 20);
        // Validate against eligible scope, not transient search/facets: a known
        // selection must remain selected when another filter yields no matches.
        $tagValidationFilters = $this->without($filters, ['price', 'rating', 'stock', 'colors', 'materials']);
        $tagValidationFilters['search'] = '';
        $tagValidationFilters['tags'] = [];
        $knownTags = array_column($this->tagFacet($base, $tagValidationFilters), 'value');
        $filters['tags'] = array_values(array_intersect($requestedTags, $knownTags));

        return $filters;
    }

    private function applyFilters(Builder|Relation $query, array $filters): Builder|Relation
    {
        $query
            ->when(($filters['tags'] ?? []) !== [], fn (Builder $products) => $products->where(function (Builder $tags) use ($filters) {
                foreach ($filters['tags'] as $tag) $tags->orWhereJsonContains('products.tags', $tag);
            }))
            ->when($filters['search'] !== '', fn (Builder $products) => $products->where(fn (Builder $search) => $search
                ->where('title', 'like', "%{$filters['search']}%")->orWhere('brand', 'like', "%{$filters['search']}%")))
            ->when($filters['category'], fn (Builder $products, string $category) => $products->whereHas('category', fn (Builder $categories) => $categories->where('slug', $category)))
            ->when($filters['minRating'] > 0, fn (Builder|Relation $products) => $products->whereIn('products.id', $this->ratedProductIds($filters['minRating'])))
            ->when($filters['variantFiltered'], fn (Builder|Relation $products) => $products->whereHas('variants', fn (Builder $variants) => $this->applyVariantConstraints($variants, $filters)));

        if (($filters['buyOnlyPriceFilters'] ?? false) && in_array($filters['sort'], ['price_asc', 'price_desc'], true)) {
            return $query->orderByRaw("CASE WHEN products.purchase_mode = 'buy' THEN 0 ELSE 1 END")->orderByRaw("CASE WHEN products.purchase_mode = 'buy' THEN products.base_price ELSE NULL END ".($filters['sort'] === 'price_desc' ? 'DESC' : 'ASC'));
        }
        return match ($filters['sort']) {
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            'rating' => $query->orderByDesc($this->publishedAverageRatingSubquery()),
            'newest' => $query->latest('products.created_at'),
            default => $query,
        };
    }

    private function applyVariantConstraints(Builder|HasMany $variants, array $filters): Builder|HasMany
    {
        return $variants->when($filters['buyOnlyPriceFilters'] ?? false, fn (Builder $v) => $v->whereHas('product', fn (Builder $p) => $p->where('purchase_mode', 'buy')))->where('enabled', true)->whereBetween('price', [$filters['minPrice'], $filters['maxPrice']])
            ->when($filters['inStock'], fn (Builder $query) => $query->where('stock', '>', 0))
            ->when($filters['colors'] !== [], fn (Builder $query) => $query->whereIn('color_name', $filters['colors']))
            ->when($filters['materials'] !== [], fn (Builder $query) => $query->whereIn('material', $filters['materials']));
    }

    /** @return array<string, mixed> */
    private function facets(Builder|Relation $base, array $filters): array
    {
        $all = $this->applyFilters(clone $base, $filters)->count();
        // A category's count must retain every other active constraint while
        // removing only the category itself, just like the color/material facets.
        $categoryBase = $filters;
        $categoryBase['category'] = null;
        $categoryCounts = $this->applyFilters(clone $base, $categoryBase)
            ->selectRaw('category_id, COUNT(DISTINCT products.id) as aggregate_count')
            ->groupBy('category_id')->pluck('aggregate_count', 'category_id')
            ->map(fn ($count) => (int) $count)->all();
        $ratingBase = $this->without($filters, ['rating']);
        $ratingProductIds = $this->applyFilters(clone $base, $ratingBase)->select('products.id');
        $ratingCounts = ProductReview::query()->published()->whereIn('product_id', $ratingProductIds)
            ->selectRaw('product_id, AVG(rating) as average_rating')->groupBy('product_id')->get()->pluck('average_rating');

        return [
            'total' => $all,
            'hasPricedProducts' => $filters['hasPricedProducts'] ?? true,
            'categoryCounts' => $categoryCounts,
            'tags' => $this->tagFacet($base, $this->without($filters, ['tags'])),
            'priceBounds' => ['min' => $filters['boundsMinPrice'], 'max' => $filters['boundsMaxPrice']],
            'colors' => $this->variantFacet($base, $this->without($filters, ['colors']), 'color_name'),
            'materials' => $this->variantFacet($base, $this->without($filters, ['materials']), 'material'),
            'ratings' => collect([4.8, 4.5])->map(fn (float $minimum) => [
                'value' => $minimum, 'label' => number_format($minimum, 1).' Stars & Up',
                'count' => $ratingCounts->filter(fn ($average) => (float) $average >= $minimum)->count(),
            ])->values()->all(),
            'hasRatings' => $ratingCounts->isNotEmpty(),
        ];
    }

    /** @return list<array{value:string,label:string,count:int,hex:?string}> */
    private function variantFacet(Builder|Relation $base, array $filters, string $column): array
    {
        $productIds = $this->applyFilters(clone $base, $filters)->select('products.id');
        $variants = ProductVariant::query()->whereIn('product_id', $productIds)->where('enabled', true);
        $this->applyVariantConstraints($variants, $filters);

        return $variants->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw($column.' as value, MIN(color_hex) as hex, COUNT(DISTINCT product_id) as aggregate_count')
            ->groupBy($column)->orderBy($column)->get()->map(fn ($facet) => [
                'value' => $facet->value, 'label' => $facet->value,
                'count' => (int) $facet->aggregate_count,
                'hex' => $column === 'color_name' ? $facet->hex : null,
            ])->all();
    }

    /** @param list<string> $without */
    private function without(array $filters, array $without): array
    {
        if (in_array('tags', $without, true)) $filters['tags'] = [];
        if (in_array('colors', $without, true)) $filters['colors'] = [];
        if (in_array('materials', $without, true)) $filters['materials'] = [];
        if (in_array('price', $without, true)) { $filters['minPrice'] = $filters['boundsMinPrice']; $filters['maxPrice'] = $filters['boundsMaxPrice']; $filters['priceRequested'] = false; }
        if (in_array('rating', $without, true)) $filters['minRating'] = 0;
        if (in_array('stock', $without, true)) $filters['inStock'] = false;
        $filters['variantFiltered'] = $this->hasVariantConstraint($filters);
        return $filters;
    }

    /** Tags inherit the caller's visibility/scope and all other active constraints. */
    private function tagFacet(Builder|Relation $base, array $filters): array
    {
        $counts = [];
        $this->applyFilters(clone $base, $filters)->reorder()->select('products.id', 'products.tags')->chunkById(250, function ($products) use (&$counts) {
            foreach ($products as $product) {
                foreach (array_unique(array_filter($product->tags ?? [], fn ($tag) => is_string($tag) && $tag !== '' && mb_strlen($tag) <= 100)) as $tag) {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        }, 'products.id', 'id');
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        $facets = [];
        foreach ($counts as $tag => $count) $facets[] = ['value' => (string) $tag, 'label' => (string) $tag, 'count' => $count];
        return $facets;
    }

    private function hasVariantConstraint(array $filters): bool
    {
        return $filters['priceRequested'] || $filters['minPrice'] !== $filters['boundsMinPrice'] || $filters['maxPrice'] !== $filters['boundsMaxPrice']
            || $filters['inStock'] || $filters['colors'] !== [] || $filters['materials'] !== [];
    }

    private function normalizedRating(Request $request): float
    {
        $rating = (float) $request->input('min_rating', 0);
        return in_array($rating, [4.5, 4.8], true) ? $rating : 0;
    }

    private function ratedProductIds(float $minimum): Builder
    {
        return ProductReview::query()->published()->select('product_id')->groupBy('product_id')
            ->havingRaw('AVG(rating) * 10 >= ?', [(int) ceil($minimum * 10)]);
    }

    private function publishedAverageRatingSubquery(): Builder
    {
        return ProductReview::query()->published()->selectRaw('AVG(rating)')->whereColumn('product_reviews.product_id', 'products.id');
    }

    private function variantQuery(Builder|Relation $base): Builder
    {
        return ProductVariant::query()->whereIn('product_id', (clone $base)->select('products.id'));
    }

    private function resolvedTheme(Request $request, Builder|Relation $base, ?string $forcedCategory): string
    {
        $scope = null;
        if ($base instanceof Relation && $base->getParent() instanceof \App\Models\Collection) $scope = 'collection:'.$base->getParent()->getKey();
        $slug = $forcedCategory ?: $request->string('category')->trim()->value();
        if (!$scope && $slug !== '') {
            $category = \App\Models\Category::query()->where('slug', $slug)->where('is_active', true)->first();
            if ($category) $scope = 'category:'.$category->getKey();
        }
        return (string) ($request->attributes->get('websiteDesignPreview') ?? app(WebsiteDesignService::class)->resolve($scope));
    }

    private function activeCategory(string $category, Builder|Relation $base): ?string
    {
        if ($category === '') return null;
        return (clone $base)->whereHas('category', fn (Builder $categories) => $categories->where('slug', $category)->where('is_active', true))->exists() ? $category : null;
    }

    /** @return list<string> */
    private function availableVariantValues(Request $request, string $parameter, string $column, Builder|Relation $base, bool $buyOnlyPrices = false): array
    {
        $requested = $request->input($parameter, []);
        $requested = is_array($requested) ? $requested : [$requested];
        $requested = array_values(array_unique(array_filter($requested, fn (mixed $value) => is_string($value) && $value !== '')));
        if ($requested === []) return [];
        return $this->variantQuery($base)->when($buyOnlyPrices, fn (Builder $v) => $v->whereHas('product', fn (Builder $p) => $p->where('purchase_mode', 'buy')))->where('enabled', true)->whereNotNull($column)->whereIn($column, $requested)
            ->pluck($column)->unique()->values()->all();
    }
}
