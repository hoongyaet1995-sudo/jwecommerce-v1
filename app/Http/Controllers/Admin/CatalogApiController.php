<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CatalogMediaReferenceSynchronizer;
use App\Domain\Catalog\CatalogSlugAllocator;
use App\Domain\Catalog\ProductHandleAllocator;
use App\Domain\Cms\MediaUrlPolicy;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductHandleRedirect;
use App\Models\ProductVariant;
use App\Services\DynamicCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogApiController extends Controller
{
    public function __construct(
        private readonly DynamicCollectionService $dynamicCollections,
        private readonly CatalogMediaReferenceSynchronizer $catalogMedia,
        private readonly CatalogSlugAllocator $catalogSlugs,
        private readonly ProductHandleAllocator $productHandles,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get()->map(fn (Category $category) => $this->categoryData($category)),
            'collections' => Collection::query()->with('products:id')->withCount('products')->orderBy('title')->get()->map(fn (Collection $collection) => $this->collectionData($collection)),
            'products' => Product::query()->with(['variants', 'images', 'collections:id', 'pdpContent', 'pdpRelatedProducts', 'pdpReviews'])->latest('updated_at')->get()->map(fn (Product $product) => $this->productData($product)),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $category = DB::transaction(function () use ($request): Category {
            $attributes = $this->categoryAttributes($request);
            $attributes['slug'] ??= $this->catalogSlugs->category($attributes['name']);
            $category = Category::query()->create($attributes);
            $this->catalogMedia->syncCategory($category);

            return $category;
        });
        $category->loadCount('products');

        return response()->json(['data' => $this->categoryData($category)], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        DB::transaction(function () use ($request, $category): void {
            $attributes = $this->categoryAttributes($request, $category);
            $attributes['slug'] ??= $category->slug;
            $category->update($attributes);
            $this->catalogMedia->syncCategory($category);
            $this->dynamicCollections->syncAll();
        });
        $category->loadCount('products');

        return response()->json(['data' => $this->categoryData($category)]);
    }

    public function destroyCategory(Category $category): JsonResponse
    {
        DB::transaction(function () use ($category): void {
            $this->catalogMedia->removeCategory($category);
            $category->delete();
        });

        return response()->json(status: 204);
    }

    public function storeCollection(Request $request): JsonResponse
    {
        $data = $this->validatedCollection($request);
        $collection = DB::transaction(function () use ($data): Collection {
            $data['slug'] ??= $this->catalogSlugs->collection($data['title']);
            $collection = Collection::query()->create($this->collectionAttributes($data));
            $collection->products()->sync($data['manualProductIds'] ?? []);
            $this->dynamicCollections->sync($collection);
            $this->catalogMedia->syncCollection($collection);

            return $collection;
        });

        return response()->json(['data' => $this->collectionData($collection->load('products:id')->loadCount('products'))], 201);
    }

    public function updateCollection(Request $request, Collection $collection): JsonResponse
    {
        $data = $this->validatedCollection($request, $collection);
        DB::transaction(function () use ($collection, $data): void {
            $data['slug'] ??= $collection->slug;
            $collection->update($this->collectionAttributes($data));
            $collection->products()->sync($data['manualProductIds'] ?? []);
            $this->dynamicCollections->sync($collection);
            $this->catalogMedia->syncCollection($collection);
        });

        return response()->json(['data' => $this->collectionData($collection->load('products:id')->loadCount('products'))]);
    }

    public function destroyCollection(Collection $collection): JsonResponse
    {
        DB::transaction(function () use ($collection): void {
            $this->catalogMedia->removeCollection($collection);
            $collection->delete();
        });

        return response()->json(status: 204);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $this->validatedProduct($request);
        $product = DB::transaction(function () use ($data): Product {
            $data['slug'] ??= $this->productHandles->allocate($data['title']);
            $product = Product::query()->create($this->productAttributes($data));
            $this->replaceProductRelations($product, $data);
            $this->catalogMedia->syncProduct($product);
            $this->dynamicCollections->syncAll();

            return $product;
        });

        return response()->json(['data' => $this->productData($this->loadProduct($product))], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $data = $this->validatedProduct($request, $product);
        DB::transaction(function () use ($product, $data): void {
            $previousHandle = $product->handle;
            $data['slug'] ??= $product->handle;
            $product->update($this->productAttributes($data));
            if ($previousHandle !== $product->handle) {
                ProductHandleRedirect::query()->where('from_handle', $product->handle)->delete();
                ProductHandleRedirect::query()->updateOrCreate(
                    ['from_handle' => $previousHandle],
                    ['product_id' => $product->id],
                );
            }
            $this->replaceProductRelations($product, $data);
            $this->catalogMedia->syncProduct($product);
            $this->dynamicCollections->syncAll();
        });

        return response()->json(['data' => $this->productData($this->loadProduct($product))]);
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        DB::transaction(function () use ($product): void {
            $product->update(['status' => 'archived']);
            $this->dynamicCollections->syncAll();
        });

        return response()->json(status: 204);
    }

    private function categoryAttributes(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('categories', 'slug')->ignore($category)],
            'parentId' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category?->id])],
            'icon' => ['nullable', 'string', 'max:100'], 'image' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'], 'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string', 'max:1000'], 'seoKeywords' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'draft'])], 'sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $this->validateCatalogMediaUrl($data['image'] ?? null, 'image');

        return ['name' => $data['name'], 'slug' => filled($data['slug'] ?? null) ? trim($data['slug']) : null, 'parent_id' => $data['parentId'] ?? null, 'icon' => $data['icon'] ?? null,
            'image_url' => $data['image'] ?? null, 'description' => $data['description'] ?? null, 'seo_title' => $data['seoTitle'] ?? null,
            'seo_description' => $data['seoDescription'] ?? null, 'seo_keywords' => $data['seoKeywords'] ?? null,
            'is_active' => $data['status'] === 'active', 'sort_order' => $data['sortOrder']];
    }

    private function validatedCollection(Request $request, ?Collection $collection = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('collections', 'slug')->ignore($collection)],
            'description' => ['nullable', 'string', 'max:5000'], 'coverImage' => ['nullable', 'string', 'max:2048'],
            'type' => ['required', Rule::in(['manual', 'dynamic'])], 'matchType' => ['nullable', Rule::in(['all', 'any'])],
            'dynamicRules' => ['nullable', 'array', 'max:100'], 'dynamicRules.*.id' => ['required', 'string', 'max:100'],
            'dynamicRules.*.field' => ['required', Rule::in(['price', 'category', 'tag', 'vendor', 'inventory'])],
            'dynamicRules.*.operator' => ['required', Rule::in(['equals', 'not_equals', 'greater_than', 'less_than', 'contains'])],
            'dynamicRules.*.value' => ['required', 'string', 'max:255'], 'manualProductIds' => ['nullable', 'array', 'max:10000'],
            'manualProductIds.*' => ['integer', 'exists:products,id'], 'status' => ['required', Rule::in(['active', 'scheduled', 'archived'])],
        ]);

        $this->validateCatalogMediaUrl($data['coverImage'] ?? null, 'coverImage');

        $data['slug'] = filled($data['slug'] ?? null) ? trim($data['slug']) : null;

        return $data;
    }

    private function collectionAttributes(array $data): array
    {
        return ['title' => $data['title'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null,
            'cover_image' => $data['coverImage'] ?? null, 'type' => $data['type'], 'match_type' => $data['matchType'] ?? null,
            'dynamic_rules' => $data['dynamicRules'] ?? [], 'status' => $data['status'], 'is_active' => $data['status'] === 'active'];
    }

    private function validatedProduct(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'], 'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'collectionIds' => ['array'], 'collectionIds.*' => ['integer', 'exists:collections,id'],
            'summary' => ['nullable', 'string', 'max:1000'], 'descriptionHtml' => ['nullable', 'string', 'max:50000'],
            'images' => ['array', 'max:12'], 'images.*' => ['required'], 'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'hasVariants' => ['required', 'boolean'], 'basePrice' => ['required', 'numeric', 'min:0'], 'baseCompareAtPrice' => ['nullable', 'numeric', 'min:0'],
            'options' => ['array', 'max:20'], 'tags' => ['array', 'max:100'], 'tags.*' => ['string', 'max:100'], 'featured' => ['required', 'boolean'],
            'seoTitle' => ['nullable', 'string', 'max:255'], 'seoDescription' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'min:1', 'max:1000'], 'variants.*.id' => ['nullable', 'string', 'max:64'],
            'variants.*.sku' => ['required', 'string', 'max:255'], 'variants.*.title' => ['required', 'string', 'max:255'],
            'variants.*.options' => ['array'], 'variants.*.price' => ['required', 'numeric', 'min:0'], 'variants.*.compareAtPrice' => ['nullable', 'numeric', 'min:0'],
            'variants.*.costPrice' => ['nullable', 'numeric', 'min:0'], 'variants.*.inventoryQuantity' => ['required', 'integer', 'min:0'],
            'variants.*.weightGrams' => ['nullable', 'integer', 'min:0'],
            'variants.*.packageLengthCm' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999'], 'variants.*.packageWidthCm' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999'], 'variants.*.packageHeightCm' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.image' => ['nullable', 'string', 'max:2048'], 'variants.*.visualType' => ['nullable', Rule::in(['none', 'color', 'image'])],
            'variants.*.colorCode' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'], 'variants.*.optionImage' => ['nullable', 'string', 'max:2048'],
            'variants.*.enabled' => ['required', 'boolean'],
            'pdpContent' => ['nullable', 'array'],
            'pdpContent.highlights' => ['nullable', 'array', 'max:20'], 'pdpContent.highlights.*.id' => ['required', 'string', 'max:100'],
            'pdpContent.highlights.*.text' => ['required', 'string', 'max:1000'], 'pdpContent.highlights.*.badge' => ['nullable', 'string', 'max:100'],
            'pdpContent.specifications' => ['nullable', 'array', 'max:100'], 'pdpContent.specifications.*.id' => ['required', 'string', 'max:100'],
            'pdpContent.specifications.*.group' => ['nullable', 'string', 'max:100'], 'pdpContent.specifications.*.label' => ['required', 'string', 'max:255'],
            'pdpContent.specifications.*.value' => ['required', 'string', 'max:2000'],
            'pdpContent.useStoreDefaultShipping' => ['nullable', 'boolean'], 'pdpContent.shippingPolicy' => ['nullable', 'array'],
            'pdpContent.shippingPolicy.shippingBadgeText' => ['nullable', 'string', 'max:255'], 'pdpContent.shippingPolicy.leadTimeText' => ['nullable', 'string', 'max:1000'],
            'pdpContent.shippingPolicy.policyTitle' => ['nullable', 'string', 'max:255'], 'pdpContent.shippingPolicy.policyContentHtml' => ['nullable', 'string', 'max:50000'],
            'pdpContent.shippingPolicy.freeShippingThreshold' => ['nullable', 'numeric', 'min:0'],
            'pdpContent.customAccordions' => ['nullable', 'array', 'max:10'], 'pdpContent.customAccordions.*.id' => ['required', 'string', 'max:100'],
            'pdpContent.customAccordions.*.title' => ['required', 'string', 'max:255'], 'pdpContent.customAccordions.*.icon' => ['nullable', 'string', 'max:100'],
            'pdpContent.customAccordions.*.content' => ['required', 'string', 'max:50000'], 'pdpContent.customAccordions.*.defaultOpen' => ['nullable', 'boolean'],
            'pdpContent.configuratorLabel' => ['nullable', 'string', 'max:100'],
            'crossSellProductIds' => ['nullable', 'array', 'max:4'], 'crossSellProductIds.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);
        $data['slug'] = filled($data['slug'] ?? null) ? trim($data['slug']) : null;
        if ($data['slug'] !== null) {
            $claimedByProduct = Product::query()
                ->where('handle', $data['slug'])
                ->when($product, fn ($query) => $query->whereKeyNot($product->id))
                ->exists();
            $claimedByRedirect = ProductHandleRedirect::query()
                ->where('from_handle', $data['slug'])
                ->when($product, fn ($query) => $query->where('product_id', '!=', $product->id))
                ->exists();
            if ($claimedByProduct || $claimedByRedirect) {
                throw ValidationException::withMessages(['slug' => 'This URL slug is already in use.']);
            }
        }
        $data['images'] = $this->normalizeProductMedia($data['images'] ?? [], $data['title']);
        foreach ($data['options'] as $optionIndex => $option) {
            if (! is_array($option) || ! is_string($option['id'] ?? null) || ! is_string($option['name'] ?? null) || ! is_array($option['values'] ?? null)) {
                throw ValidationException::withMessages(["options.$optionIndex" => 'An option must include an id, name, and values.']);
            }
            if (isset($option['displayType']) && ! in_array($option['displayType'], ['color_palette', 'option_image', 'pills', 'dropdown'], true)) {
                throw ValidationException::withMessages(["options.$optionIndex.displayType" => 'The option display mode is invalid.']);
            }
            $values = array_map('strval', $option['values']);
            foreach (($option['valueVisuals'] ?? []) as $value => $visual) {
                $path = "options.$optionIndex.valueVisuals.$value";
                if (! in_array((string) $value, $values, true) || ! is_array($visual)) {
                    throw ValidationException::withMessages([$path => 'A visual must belong to an existing option value.']);
                }
                $visualType = $visual['visualType'] ?? 'none';
                $hasColor = filled($visual['colorCode'] ?? null);
                $hasImage = filled($visual['optionImage'] ?? null);
                if (! in_array($visualType, ['none', 'color', 'image'], true) || ($visualType === 'color' && (! $hasColor || $hasImage)) || ($visualType === 'image' && (! $hasImage || $hasColor)) || ($visualType === 'none' && ($hasColor || $hasImage))) {
                    throw ValidationException::withMessages(["$path.visualType" => 'An option visual must be exactly one of color, image, or none.']);
                }
                if ($hasColor && (! is_string($visual['colorCode']) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $visual['colorCode']))) {
                    throw ValidationException::withMessages(["$path.colorCode" => 'The color code must be a six-digit hex value.']);
                }
                if ($hasImage) {
                    $this->validateCatalogMediaUrl($visual['optionImage'], "$path.optionImage");
                }
                $data['options'][$optionIndex]['valueVisuals'][$value] = ['visualType' => $visualType, 'colorCode' => $hasColor ? $visual['colorCode'] : null, 'optionImage' => $hasImage ? $visual['optionImage'] : null];
            }
        }
        $seen = [];
        foreach ($data['variants'] as $index => $variant) {
            $visualType = $variant['visualType'] ?? 'none';
            $hasColor = filled($variant['colorCode'] ?? null);
            $hasImage = filled($variant['optionImage'] ?? null);
            if (($visualType === 'color' && (! $hasColor || $hasImage)) || ($visualType === 'image' && (! $hasImage || $hasColor)) || ($visualType === 'none' && ($hasColor || $hasImage))) {
                throw ValidationException::withMessages(["variants.$index.visualType" => 'A variant visual must be exactly one of color, image, or none.']);
            }
            if ($hasImage) {
                $this->validateCatalogMediaUrl($variant['optionImage'], "variants.$index.optionImage");
            }
            $data['variants'][$index]['visualType'] = $visualType;
            $data['variants'][$index]['colorCode'] = $hasColor ? $variant['colorCode'] : null;
            $data['variants'][$index]['optionImage'] = $hasImage ? $variant['optionImage'] : null;
            $id = ctype_digit((string) ($variant['id'] ?? '')) ? (int) $variant['id'] : null;
            if (isset($seen[$variant['sku']]) || ProductVariant::query()->where('sku', $variant['sku'])->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(["variants.$index.sku" => 'The SKU has already been taken.']);
            }
            $seen[$variant['sku']] = true;
        }

        $this->validatePdpRelations($data, $product);

        return $data;
    }

    private function productAttributes(array $data): array
    {
        return ['title' => $data['title'], 'handle' => $data['slug'], 'brand' => $data['brand'] ?? null, 'category_id' => $data['categoryId'] ?? null,
            'subtitle' => $data['summary'] ?? null, 'description' => $data['descriptionHtml'] ?? null, 'status' => $data['status'],
            'base_price' => $this->cents($data['basePrice']), 'compare_at_price' => isset($data['baseCompareAtPrice']) ? $this->cents($data['baseCompareAtPrice']) : null,
            'has_variants' => $data['hasVariants'], 'options' => $data['options'], 'tags' => $data['tags'], 'is_featured' => $data['featured'],
            'seo_title' => $data['seoTitle'] ?? null, 'seo_description' => $data['seoDescription'] ?? null];
    }

    private function replaceProductRelations(Product $product, array $data): void
    {
        $product->collections()->sync($data['collectionIds']);
        $existing = $product->variants()->get()->keyBy('id');
        $kept = [];
        foreach ($data['variants'] as $variant) {
            $id = ctype_digit((string) ($variant['id'] ?? '')) ? (int) $variant['id'] : null;
            $attributes = ['sku' => $variant['sku'], 'title' => $variant['title'], 'options' => $variant['options'],
                'color_name' => $variant['options']['Color'] ?? null, 'size' => $variant['options']['Size'] ?? null, 'material' => $variant['options']['Material'] ?? null,
                'price' => $this->cents($variant['price']), 'compare_at_price' => isset($variant['compareAtPrice']) ? $this->cents($variant['compareAtPrice']) : null,
                'cost_price' => isset($variant['costPrice']) ? $this->cents($variant['costPrice']) : null, 'stock' => $variant['inventoryQuantity'],
                'weight_grams' => $variant['weightGrams'] ?? null, 'package_length_cm' => $variant['packageLengthCm'] ?? null, 'package_width_cm' => $variant['packageWidthCm'] ?? null, 'package_height_cm' => $variant['packageHeightCm'] ?? null, 'barcode' => $variant['barcode'] ?? null, 'image_url' => $variant['image'] ?? null,
                'visual_type' => $variant['visualType'], 'color_code' => $variant['colorCode'], 'option_image' => $variant['optionImage'], 'enabled' => $variant['enabled']];
            $model = $id && $existing->has($id) ? $existing[$id] : $product->variants()->create($attributes);
            if ($model->exists && $id && $existing->has($id)) {
                $model->update($attributes);
            }
            $kept[] = $model->id;
        }
        $product->variants()->whereNotIn('id', $kept)->delete();
        $product->images()->delete();
        foreach ($data['images'] as $position => $image) {
            $product->images()->create(['path' => $image['path'], 'alt' => $image['alt'], 'position' => $position]);
        }

        $this->replaceProductPdpRelations($product, $data);
    }

    private function categoryData(Category $category): array
    {
        return ['id' => (string) $category->id, 'name' => $category->name, 'slug' => $category->slug, 'parentId' => $category->parent_id ? (string) $category->parent_id : null, 'icon' => $category->icon ?? 'FolderTree', 'image' => $category->image_url, 'description' => $category->description, 'seoTitle' => $category->seo_title, 'seoDescription' => $category->seo_description, 'seoKeywords' => $category->seo_keywords, 'status' => $category->is_active ? 'active' : 'draft', 'sortOrder' => $category->sort_order, 'productCount' => $category->products_count ?? $category->products()->count(), 'createdAt' => $category->created_at?->toDateString()];
    }

    private function collectionData(Collection $collection): array
    {
        return ['id' => (string) $collection->id, 'title' => $collection->title, 'slug' => $collection->slug, 'description' => $collection->description, 'coverImage' => $collection->cover_image, 'type' => $collection->type, 'matchType' => $collection->match_type, 'dynamicRules' => $collection->dynamic_rules ?? [], 'manualProductIds' => $collection->relationLoaded('products') ? $collection->products->pluck('id')->map(fn ($id) => (string) $id)->all() : [], 'status' => $collection->status, 'productCount' => $collection->products_count ?? $collection->products()->count(), 'createdAt' => $collection->created_at?->toDateString()];
    }

    private function productData(Product $product): array
    {
        $pdpContent = $product->pdpContent;

        return ['id' => (string) $product->id, 'title' => $product->title, 'slug' => $product->handle, 'brand' => $product->brand ?? '', 'categoryId' => $product->category_id ? (string) $product->category_id : '', 'collectionIds' => $product->collections->pluck('id')->map(fn ($id) => (string) $id)->all(), 'descriptionHtml' => $product->description ?? '', 'summary' => $product->subtitle ?? '', 'images' => $product->images->sortBy('position')->values()->map(fn ($image) => ['id' => (string) $image->id, 'path' => $image->path, 'alt' => $image->alt ?? '', 'position' => $image->position])->all(), 'status' => $product->status, 'hasVariants' => $product->has_variants, 'basePrice' => $product->base_price / 100, 'baseCompareAtPrice' => $product->compare_at_price !== null ? $product->compare_at_price / 100 : null, 'options' => $product->options ?? [], 'variants' => $product->variants->map(fn (ProductVariant $v) => ['id' => (string) $v->id, 'sku' => $v->sku, 'title' => $v->title, 'options' => $v->options ?? [], 'price' => $v->price / 100, 'compareAtPrice' => $v->compare_at_price !== null ? $v->compare_at_price / 100 : null, 'costPrice' => $v->cost_price !== null ? $v->cost_price / 100 : null, 'inventoryQuantity' => $v->stock, 'weightGrams' => $v->weight_grams, 'packageLengthCm' => $v->package_length_cm, 'packageWidthCm' => $v->package_width_cm, 'packageHeightCm' => $v->package_height_cm, 'barcode' => $v->barcode, 'image' => $v->image_url, 'visualType' => $v->visual_type, 'colorCode' => $v->color_code, 'optionImage' => $v->option_image, 'enabled' => $v->enabled])->values(), 'tags' => $product->tags ?? [], 'totalInventory' => $product->variants->sum('stock'), 'featured' => $product->is_featured, 'seoTitle' => $product->seo_title, 'seoDescription' => $product->seo_description,
            'pdpContent' => ['highlights' => $pdpContent?->highlights ?? [], 'specifications' => $pdpContent?->specifications ?? [], 'useStoreDefaultShipping' => $pdpContent?->use_store_default_shipping ?? true, 'shippingPolicy' => $pdpContent?->shipping_policy, 'customAccordions' => $pdpContent?->custom_accordions ?? [], 'configuratorLabel' => $pdpContent?->configurator_label],
            'crossSellProductIds' => $product->pdpRelatedProducts->pluck('related_product_id')->map(fn ($id) => (string) $id)->values()->all(),
            'createdAt' => $product->created_at?->toDateString(), 'updatedAt' => $product->updated_at?->toDateString()];
    }

    private function loadProduct(Product $product): Product
    {
        return $product->load(['variants', 'images', 'collections:id', 'pdpContent', 'pdpRelatedProducts']);
    }

    private function validatePdpRelations(array $data, ?Product $product): void
    {
        $relatedIds = array_map('intval', $data['crossSellProductIds'] ?? []);
        if ($relatedIds === []) {
            return;
        }

        $activeIds = Product::query()->whereIn('id', $relatedIds)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($relatedIds as $index => $relatedId) {
            if (($product && $relatedId === $product->id) || ! in_array($relatedId, $activeIds, true)) {
                throw ValidationException::withMessages(["crossSellProductIds.$index" => 'A related product must be active and cannot be this product.']);
            }
        }
    }

    private function replaceProductPdpRelations(Product $product, array $data): void
    {
        if (array_key_exists('pdpContent', $data)) {
            $content = $data['pdpContent'] ?? [];
            $product->pdpContent()->updateOrCreate([], [
                'highlights' => $content['highlights'] ?? [],
                'specifications' => $content['specifications'] ?? [],
                'use_store_default_shipping' => $content['useStoreDefaultShipping'] ?? true,
                'shipping_policy' => ($content['useStoreDefaultShipping'] ?? true) ? null : ($content['shippingPolicy'] ?? null),
                'custom_accordions' => $content['customAccordions'] ?? [],
                'configurator_label' => $content['configuratorLabel'] ?? null,
            ]);
        }

        if (array_key_exists('crossSellProductIds', $data)) {
            $product->pdpRelatedProducts()->delete();
            foreach (array_values($data['crossSellProductIds'] ?? []) as $sortOrder => $relatedProductId) {
                $product->pdpRelatedProducts()->create(['related_product_id' => (int) $relatedProductId, 'sort_order' => $sortOrder]);
            }
        }
    }

    private function cents(int|float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /** @param array<int, mixed> $images @return array<int, array{path: string, alt: string}> */
    private function normalizeProductMedia(array $images, string $productTitle): array
    {
        $normalized = [];
        $errors = [];

        foreach ($images as $index => $image) {
            $path = is_string($image) ? $image : (is_array($image) ? ($image['path'] ?? null) : null);
            $alt = is_array($image) ? ($image['alt'] ?? $productTitle) : $productTitle;

            if (! is_string($path) || $path === '' || strlen($path) > 2048 || ! $this->isAllowedCatalogMediaUrl($path)) {
                $errors["images.{$index}.path"] = ['Media must use a managed asset URL or a safe public HTTPS URL.'];

                continue;
            }

            if (! is_string($alt) || strlen($alt) > 500) {
                $errors["images.{$index}.alt"] = ['The Alt text must be a string of at most 500 characters.'];

                continue;
            }

            $normalized[] = ['path' => $path, 'alt' => trim($alt)];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * The pre-existing catalog ships a finite set of first-party placeholder
     * images under public/images/catalog. Keep those records editable during
     * the managed-upload migration without opening relative URL imports.
     */
    private function isAllowedCatalogMediaUrl(string $url): bool
    {
        if (MediaUrlPolicy::isSafe($url)) {
            return true;
        }

        if (preg_match('#\A/images/catalog/[a-z0-9][a-z0-9_-]*\.(?:jpe?g|png|webp|gif)\z#iD', $url) !== 1) {
            return false;
        }

        return is_file(public_path(ltrim($url, '/')));
    }

    private function validateCatalogMediaUrl(?string $url, string $field): void
    {
        if ($url !== null && $url !== '' && ! $this->isAllowedCatalogMediaUrl($url)) {
            throw ValidationException::withMessages([
                $field => 'Media must use a managed asset URL, a safe public HTTPS URL, or a bundled catalog image.',
            ]);
        }
    }
}
