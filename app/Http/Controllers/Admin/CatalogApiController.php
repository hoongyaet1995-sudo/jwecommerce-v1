<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\DynamicCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogApiController extends Controller
{
    public function __construct(private readonly DynamicCollectionService $dynamicCollections) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get()->map(fn (Category $category) => $this->categoryData($category)),
            'collections' => Collection::query()->with('products:id')->withCount('products')->orderBy('title')->get()->map(fn (Collection $collection) => $this->collectionData($collection)),
            'products' => Product::query()->with(['variants', 'images', 'collections:id'])->latest('updated_at')->get()->map(fn (Product $product) => $this->productData($product)),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $category = Category::query()->create($this->categoryAttributes($request));
        $category->loadCount('products');
        return response()->json(['data' => $this->categoryData($category)], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        DB::transaction(function () use ($request, $category): void {
            $category->update($this->categoryAttributes($request, $category));
            $this->dynamicCollections->syncAll();
        });
        $category->loadCount('products');
        return response()->json(['data' => $this->categoryData($category)]);
    }

    public function destroyCategory(Category $category): JsonResponse
    {
        $category->delete();
        return response()->json(status: 204);
    }

    public function storeCollection(Request $request): JsonResponse
    {
        $data = $this->validatedCollection($request);
        $collection = DB::transaction(function () use ($data): Collection {
            $collection = Collection::query()->create($this->collectionAttributes($data));
            $collection->products()->sync($data['manualProductIds'] ?? []);
            $this->dynamicCollections->sync($collection);
            return $collection;
        });
        return response()->json(['data' => $this->collectionData($collection->load('products:id')->loadCount('products'))], 201);
    }

    public function updateCollection(Request $request, Collection $collection): JsonResponse
    {
        $data = $this->validatedCollection($request, $collection);
        DB::transaction(function () use ($collection, $data): void {
            $collection->update($this->collectionAttributes($data));
            $collection->products()->sync($data['manualProductIds'] ?? []);
            $this->dynamicCollections->sync($collection);
        });
        return response()->json(['data' => $this->collectionData($collection->load('products:id')->loadCount('products'))]);
    }

    public function destroyCollection(Collection $collection): JsonResponse
    {
        $collection->delete();
        return response()->json(status: 204);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $this->validatedProduct($request);
        $product = DB::transaction(function () use ($data): Product {
            $product = Product::query()->create($this->productAttributes($data));
            $this->replaceProductRelations($product, $data);
            $this->dynamicCollections->syncAll();
            return $product;
        });
        return response()->json(['data' => $this->productData($this->loadProduct($product))], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $data = $this->validatedProduct($request, $product);
        DB::transaction(function () use ($product, $data): void {
            $product->update($this->productAttributes($data));
            $this->replaceProductRelations($product, $data);
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
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('categories', 'slug')->ignore($category)],
            'parentId' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category?->id])],
            'icon' => ['nullable', 'string', 'max:100'], 'image' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'], 'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string', 'max:1000'], 'seoKeywords' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'draft'])], 'sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        return ['name' => $data['name'], 'slug' => $data['slug'], 'parent_id' => $data['parentId'] ?? null, 'icon' => $data['icon'] ?? null,
            'image_url' => $data['image'] ?? null, 'description' => $data['description'] ?? null, 'seo_title' => $data['seoTitle'] ?? null,
            'seo_description' => $data['seoDescription'] ?? null, 'seo_keywords' => $data['seoKeywords'] ?? null,
            'is_active' => $data['status'] === 'active', 'sort_order' => $data['sortOrder']];
    }

    private function validatedCollection(Request $request, ?Collection $collection = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('collections', 'slug')->ignore($collection)],
            'description' => ['nullable', 'string', 'max:5000'], 'coverImage' => ['nullable', 'string', 'max:2048'],
            'type' => ['required', Rule::in(['manual', 'dynamic'])], 'matchType' => ['nullable', Rule::in(['all', 'any'])],
            'dynamicRules' => ['nullable', 'array', 'max:100'], 'dynamicRules.*.id' => ['required', 'string', 'max:100'],
            'dynamicRules.*.field' => ['required', Rule::in(['price', 'category', 'tag', 'vendor', 'inventory'])],
            'dynamicRules.*.operator' => ['required', Rule::in(['equals', 'not_equals', 'greater_than', 'less_than', 'contains'])],
            'dynamicRules.*.value' => ['required', 'string', 'max:255'], 'manualProductIds' => ['nullable', 'array', 'max:10000'],
            'manualProductIds.*' => ['integer', 'exists:products,id'], 'status' => ['required', Rule::in(['active', 'scheduled', 'archived'])],
        ]);
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
            'title' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('products', 'handle')->ignore($product)],
            'brand' => ['nullable', 'string', 'max:255'], 'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'collectionIds' => ['array'], 'collectionIds.*' => ['integer', 'exists:collections,id'],
            'summary' => ['nullable', 'string', 'max:1000'], 'descriptionHtml' => ['nullable', 'string', 'max:50000'],
            'images' => ['array', 'max:50'], 'images.*' => ['string', 'max:2048'], 'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'hasVariants' => ['required', 'boolean'], 'basePrice' => ['required', 'numeric', 'min:0'], 'baseCompareAtPrice' => ['nullable', 'numeric', 'min:0'],
            'options' => ['array', 'max:20'], 'tags' => ['array', 'max:100'], 'tags.*' => ['string', 'max:100'], 'featured' => ['required', 'boolean'],
            'seoTitle' => ['nullable', 'string', 'max:255'], 'seoDescription' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'min:1', 'max:1000'], 'variants.*.id' => ['nullable', 'string', 'max:64'],
            'variants.*.sku' => ['required', 'string', 'max:255'], 'variants.*.title' => ['required', 'string', 'max:255'],
            'variants.*.options' => ['array'], 'variants.*.price' => ['required', 'numeric', 'min:0'], 'variants.*.compareAtPrice' => ['nullable', 'numeric', 'min:0'],
            'variants.*.costPrice' => ['nullable', 'numeric', 'min:0'], 'variants.*.inventoryQuantity' => ['required', 'integer', 'min:0'],
            'variants.*.weightGrams' => ['nullable', 'integer', 'min:0'], 'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.image' => ['nullable', 'string', 'max:2048'], 'variants.*.enabled' => ['required', 'boolean'],
        ]);
        $seen = [];
        foreach ($data['variants'] as $index => $variant) {
            $id = ctype_digit((string) ($variant['id'] ?? '')) ? (int) $variant['id'] : null;
            if (isset($seen[$variant['sku']]) || ProductVariant::query()->where('sku', $variant['sku'])->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(["variants.$index.sku" => 'The SKU has already been taken.']);
            }
            $seen[$variant['sku']] = true;
        }
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
        $existing = $product->variants()->get()->keyBy('id'); $kept = [];
        foreach ($data['variants'] as $variant) {
            $id = ctype_digit((string) ($variant['id'] ?? '')) ? (int) $variant['id'] : null;
            $attributes = ['sku' => $variant['sku'], 'title' => $variant['title'], 'options' => $variant['options'],
                'color_name' => $variant['options']['Color'] ?? null, 'size' => $variant['options']['Size'] ?? null, 'material' => $variant['options']['Material'] ?? null,
                'price' => $this->cents($variant['price']), 'compare_at_price' => isset($variant['compareAtPrice']) ? $this->cents($variant['compareAtPrice']) : null,
                'cost_price' => isset($variant['costPrice']) ? $this->cents($variant['costPrice']) : null, 'stock' => $variant['inventoryQuantity'],
                'weight_grams' => $variant['weightGrams'] ?? null, 'barcode' => $variant['barcode'] ?? null, 'image_url' => $variant['image'] ?? null, 'enabled' => $variant['enabled']];
            $model = $id && $existing->has($id) ? $existing[$id] : $product->variants()->create($attributes);
            if ($model->exists && $id && $existing->has($id)) $model->update($attributes);
            $kept[] = $model->id;
        }
        $product->variants()->whereNotIn('id', $kept)->delete();
        $product->images()->delete();
        foreach ($data['images'] as $position => $path) $product->images()->create(['path' => $path, 'alt' => $product->title, 'position' => $position]);
    }

    private function categoryData(Category $category): array { return ['id'=>(string)$category->id,'name'=>$category->name,'slug'=>$category->slug,'parentId'=>$category->parent_id ? (string)$category->parent_id : null,'icon'=>$category->icon ?? 'FolderTree','image'=>$category->image_url,'description'=>$category->description,'seoTitle'=>$category->seo_title,'seoDescription'=>$category->seo_description,'seoKeywords'=>$category->seo_keywords,'status'=>$category->is_active?'active':'draft','sortOrder'=>$category->sort_order,'productCount'=>$category->products_count ?? $category->products()->count(),'createdAt'=>$category->created_at?->toDateString()]; }
    private function collectionData(Collection $collection): array { return ['id'=>(string)$collection->id,'title'=>$collection->title,'slug'=>$collection->slug,'description'=>$collection->description,'coverImage'=>$collection->cover_image,'type'=>$collection->type,'matchType'=>$collection->match_type,'dynamicRules'=>$collection->dynamic_rules ?? [],'manualProductIds'=>$collection->relationLoaded('products')?$collection->products->pluck('id')->map(fn($id)=>(string)$id)->all():[],'status'=>$collection->status,'productCount'=>$collection->products_count ?? $collection->products()->count(),'createdAt'=>$collection->created_at?->toDateString()]; }
    private function productData(Product $product): array { return ['id'=>(string)$product->id,'title'=>$product->title,'slug'=>$product->handle,'brand'=>$product->brand ?? '','categoryId'=>$product->category_id?(string)$product->category_id:'','collectionIds'=>$product->collections->pluck('id')->map(fn($id)=>(string)$id)->all(),'descriptionHtml'=>$product->description ?? '','summary'=>$product->subtitle ?? '','images'=>$product->images->sortBy('position')->pluck('path')->values()->all(),'status'=>$product->status,'hasVariants'=>$product->has_variants,'basePrice'=>$product->base_price/100,'baseCompareAtPrice'=>$product->compare_at_price!==null?$product->compare_at_price/100:null,'options'=>$product->options ?? [],'variants'=>$product->variants->map(fn(ProductVariant $v)=>['id'=>(string)$v->id,'sku'=>$v->sku,'title'=>$v->title,'options'=>$v->options ?? [],'price'=>$v->price/100,'compareAtPrice'=>$v->compare_at_price!==null?$v->compare_at_price/100:null,'costPrice'=>$v->cost_price!==null?$v->cost_price/100:null,'inventoryQuantity'=>$v->stock,'weightGrams'=>$v->weight_grams,'barcode'=>$v->barcode,'image'=>$v->image_url,'enabled'=>$v->enabled])->values(),'tags'=>$product->tags ?? [],'totalInventory'=>$product->variants->sum('stock'),'featured'=>$product->is_featured,'seoTitle'=>$product->seo_title,'seoDescription'=>$product->seo_description,'createdAt'=>$product->created_at?->toDateString(),'updatedAt'=>$product->updated_at?->toDateString()]; }
    private function loadProduct(Product $product): Product { return $product->load(['variants','images','collections:id']); }
    private function cents(int|float|string $amount): int { return (int) round((float) $amount * 100); }
}
