<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection as ProductCollection;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductHandleRedirect;
use App\Models\SocialLink;
use App\Models\StoreSetting;
use App\Services\CatalogFilterService;
use App\Services\CatalogListingSettingsService;
use App\Services\PaymentConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly CatalogFilterService $catalogFilters,
        private readonly CatalogListingSettingsService $catalogListingSettings,
        private readonly PaymentConfigurationService $paymentConfiguration,
    )
    {
    }

    public function home(): View|\Illuminate\Http\Response
    {
        $homepageId = app(\App\Domain\Cms\HomepageSelection::class)->id();
        if ($homepageId) {
            $homepage = Page::with('publishedDocument')->findOrFail($homepageId);
            abort_unless($homepage->status === 'published' && $homepage->publishedDocument, 404);
            return app(StorefrontPageController::class)($homepage->publishedDocument->slug);
        }
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
        return $this->listingView($request, $this->listingData($request, Product::query()->where('status', 'active')) + [
            'categories' => Category::query()->where('is_active', true)->get(),
            'collection' => null,
            'listingRouteName' => 'storefront.index',
            'listingRouteParameters' => [],
        ]);
    }

    private function listingView(Request $request, array $data): View
    {
        $category = !empty($data['category']) ? Category::where('slug', $data['category'])->first() : null;
        $collection = $data['collection'] ?? null;
        $scope = $collection ? 'collection:'.$collection->id : ($category ? 'category:'.$category->id : null);
        $theme = $request->attributes->get('websiteDesignPreview') ?? app(\App\Services\WebsiteDesignService::class)->resolve($scope);
        return view($theme === 'home_deco' ? 'storefront.themes.home-deco-listing' : 'storefront.index', $data + ['websiteDesignTheme'=>$theme, 'listingCategory'=>$category]);
    }

    private function listingData(Request $request, $query, ?string $forcedCategory = null): array
    {
        return $this->catalogFilters->for($request, $query, $forcedCategory)->toViewData() + [
            'catalogListingSettings' => $this->catalogListingSettings->live(),
        ];
    }

    public function show(Request $request, string $handle): View|RedirectResponse
    {
        $product = Product::query()->where('handle', $handle)->first();
        if (! $product) {
            $redirect = ProductHandleRedirect::query()->with('product')->where('from_handle', $handle)->first();
            abort_unless($redirect?->product, 404);

            return redirect()->route('storefront.products.show', ['handle' => $redirect->product->handle] + $request->query(), 301);
        }
        abort_unless($product->status === 'active', 404);

        $product->load([
            'category',
            'images',
            'variants',
            'pdpContent',
            'reviews' => fn ($query) => $query->published()
                ->with(['media' => fn ($media) => $media->whereNotNull('published_at')])
                ->oldest('published_at'),
            'pdpRelatedProducts.relatedProduct.images',
            'pdpRelatedProducts.relatedProduct.variants',
        ]);
        $selectedVariant = app(\App\Services\StorefrontVariantData::class)->select($product->variants, $request->integer('variant'));
        $images = $product->images->sortBy('position')->values();
        $selectedImage = $images->get($request->integer('image'))
            ?? $images->firstWhere('product_variant_id', $selectedVariant?->id)
            ?? $images->first();
        $shopName = trim((string) data_get(StoreSetting::query()->where('key', 'shop_name')->value('value'), 'data'));

        return view(app(\App\Services\WebsiteDesignService::class)->surface($product->category_id ? 'category:'.$product->category_id : null) === 'home_deco' ? 'storefront.themes.home-deco-pdp' : 'storefront.show', [
            'product' => $product,
            'selectedVariant' => $selectedVariant,
            'selectedImage' => $selectedImage,
            'images' => $images,
            'pdpBrand' => filled($product->brand) ? $product->brand : $shopName,
            'pdpPaymentMethods' => $this->paymentConfiguration->configuredStorefrontMethods(),
            'companions' => $product->pdpRelatedProducts
                ->map(fn ($relation) => $relation->relatedProduct)
                ->filter(fn (?Product $relatedProduct) => $relatedProduct?->status === 'active')
                ->values(),
        ]);
    }

    public function collection(Request $request, ProductCollection $collection): View
    {
        abort_unless($collection->is_active, 404);

        return $this->listingView($request, $this->listingData($request, $collection->products()->where('status', 'active')) + [
            'collection' => $collection,
            'categories' => Category::query()->where('is_active', true)->get(),
            'listingRouteName' => 'storefront.collections.show',
            'listingRouteParameters' => ['collection' => $collection],
        ]);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        return $this->listingView($request, $this->listingData(
            $request,
            $category->products()->where('status', 'active'),
            $category->slug,
        ) + [
            'collection' => null,
            'categories' => Category::query()->where('is_active', true)->get(),
            'listingRouteName' => 'storefront.categories.show',
            'listingRouteParameters' => ['category' => $category],
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
