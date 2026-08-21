<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection as ProductCollection;
use App\Models\Product;
use App\Models\Page;
use App\Models\SocialLink;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View
    {
        $heroSection = Page::query()
            ->where('slug', 'home')
            ->where('is_published', true)
            ->with(['sections' => fn ($query) => $query->where('type', 'hero')->orderBy('position')])
            ->first()
            ?->sections
            ->first();

        return view('storefront.home', [
            'categories' => Category::query()->where('is_active', true)->get(),
            'collections' => ProductCollection::query()->where('is_active', true)->withCount('products')->orderBy('title')->get(),
            'products' => Product::query()->where('status', 'active')->with(['variants', 'images'])->get(),
            'socialLinks' => SocialLink::query()->where('is_active', true)->get(),
            'heroContent' => $heroSection?->content ?? [],
        ]);
    }

    public function index(Request $request): View
    {
        return view('storefront.index', $this->listingData($request, Product::query()->where('status', 'active')) + [
            'categories' => Category::query()->where('is_active', true)->get(),
            'collection' => null,
            'listingRouteName' => 'storefront.index',
            'listingRouteParameters' => [],
        ]);
    }

    private function listingData(Request $request, $query): array
    {
        $search = $request->string('search')->trim()->value();
        $category = $request->string('category')->trim()->value();
        $sort = $request->string('sort')->value();
        $maxPrice = $request->integer('max_price') ?: null;
        $inStock = $request->boolean('in_stock');
        $color = $request->string('color')->trim()->value();
        $layout = $request->string('layout')->value() === 'list' ? 'list' : 'grid';
        $products = $query->with(['category', 'images', 'variants'])
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested->where('title', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%")))
            ->when($category, fn ($query) => $query->whereHas('category', fn ($nested) => $nested->where('slug', $category)))
            ->when($maxPrice, fn ($query) => $query->where('base_price', '<=', $maxPrice))
            ->when($inStock, fn ($query) => $query->whereHas('variants', fn ($nested) => $nested->where('stock', '>', 0)))
            ->when($color, fn ($query) => $query->whereHas('variants', fn ($nested) => $nested->where('color_name', $color)))
            ->when($sort === 'price_asc', fn ($query) => $query->orderBy('base_price'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('base_price'))
            ->get();
        $colors = $products->flatMap(fn (Product $product) => $product->variants->pluck('color_name'))->filter()->unique()->sort()->values();
        return compact('products', 'search', 'category', 'sort', 'maxPrice', 'inStock', 'color', 'colors', 'layout');
    }

    public function show(Request $request, Product $product): View
    {
        abort_unless($product->status === 'active', 404);

        $product->load(['category', 'images', 'variants']);
        $selectedVariant = $product->variants->firstWhere('id', $request->integer('variant'))
            ?? $product->variants->firstWhere('stock', '>', 0)
            ?? $product->variants->first();
        $images = $product->images->sortBy('position')->values();
        $selectedImage = $images->get($request->integer('image'))
            ?? $images->firstWhere('product_variant_id', $selectedVariant?->id)
            ?? $images->first();

        return view('storefront.show', [
            'product' => $product,
            'selectedVariant' => $selectedVariant,
            'selectedImage' => $selectedImage,
            'images' => $images,
            'companions' => Product::query()
                ->where('status', 'active')
                ->whereKeyNot($product->id)
                ->with(['images', 'variants'])
                ->orderBy('title')
                ->take(2)
                ->get(),
        ]);
    }

    public function collection(Request $request, ProductCollection $collection): View
    {
        abort_unless($collection->is_active, 404);

        return view('storefront.index', $this->listingData($request, $collection->products()->where('status', 'active')) + [
            'collection' => $collection,
            'categories' => Category::query()->where('is_active', true)->get(),
            'listingRouteName' => 'storefront.collections.show',
            'listingRouteParameters' => ['collection' => $collection],
        ]);
    }

    public function page(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('storefront.page', [
            'page' => $page->load(['sections' => fn ($query) => $query->orderBy('position')]),
            'products' => Product::query()->where('status', 'active')->with(['images', 'variants'])->get(),
        ]);
    }

}
