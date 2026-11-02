@php
    $listingTitle=$collection?->title ?? $listingCategory?->name ?? ($catalogListingSettings['title'] ?: 'All products');
    $description=$collection?->subtitle ?: ($listingCategory?->description ?: ($listingCategory ? '' : $catalogListingSettings['description']));
    $heroImage=$collection?->cover_image ?: $listingCategory?->image_url;
    $isAllProducts=!$collection&&!$listingCategory;
    $allProductsMedia=$isAllProducts?app(\App\Services\WebsiteThemeCustomization::class)->media('jewelry','allProducts',''):null;
    if($isAllProducts)$heroImage=$allProductsMedia['hidden']?'':$allProductsMedia['desktop'];
    $resetUrl=route($listingRouteName,$listingRouteParameters);
    $activeCount=($filters['priceRequested']?1:0)+count($filters['colors'])+count($filters['materials'])+($filters['inStock']?1:0)+($filters['minRating']>0?1:0);

 $catalogBannerMedia=app(\App\Services\CatalogBannerMedia::class)->resolve($collection ?? $listingCategory, $listingTitle);
 if($catalogBannerMedia['hidden']) $heroImage='';
@endphp
<x-layouts.app :title="$listingTitle.' | '.$shopBrandName">


    <div class="jg-theme" data-website-theme="jewelry" data-commerce-collection-anatomy x-data="{filtersOpen:false}" @keydown.escape.window="filtersOpen=false">
        <x-storefront.nav active="plp" />
            @if(filled($heroImage))
            <section class="jg-listing-hero">@if($isAllProducts)<div data-all-products-media>@include('storefront.partials.theme-media',['media'=>$allProductsMedia])</div>@else
@include('storefront.partials.catalog-banner',['media'=>$catalogBannerMedia,'imageClass'=>''])
@endif</section>
            @endif

        <div class="frame"><main class="container">
            <div class="breadcrumbs"><a href="/">Home</a><span>/</span><a href="{{ route('storefront.index') }}">Shop</a>@if($collection || $listingCategory)<span>/</span><span>{{ $listingTitle }}</span>@endif</div>
            <header class="jg-listing-heading"><h1>{{ $listingTitle }}</h1>@if(filled($description))<p>{{ strip_tags($description) }}</p>@endif</header>
            <div class="catalogbar"><span>{{ $products->total() }} {{ $products->total()===1?'product':'products' }}</span><div class="barActions"><button type="button" @click="filtersOpen=true" aria-label="Open filters"><i data-lucide="sliders-horizontal" class="icon"></i>Filters @if($activeCount)({{ $activeCount }})
@endif</button><form method="GET" action="{{ $resetUrl }}">
                @foreach(array_intersect_key($listingRouteParameters,array_flip(['theme','scope','customization'])) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                @foreach(request()->except(['sort','page','theme','scope']) as $key=>$value)
                    @if(is_array($value))@foreach($value as $entry)<input type="hidden" name="{{ $key }}[]" value="{{ $entry }}">@endforeach
                    @elseif(filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <select name="sort" aria-label="Sort products" onchange="this.form.submit()"><option value="">Featured</option><option value="price_asc" @selected($sort==='price_asc')>Price: low to high</option><option value="price_desc" @selected($sort==='price_desc')>Price: high to low</option><option value="newest" @selected($sort==='newest')>Newest</option>@if($facets['hasRatings'])<option value="rating" @selected($sort==='rating')>Customer rating</option>@endif</select>
            </form></div></div>
            <div class="products" data-commerce-product-grid-layout="jewelry-studio-grid">
            @forelse($products as $product)
                @php($variant=$product->variants->first())
                <article class="product" data-storefront-product-card>@if(auth('customer')->check())<form class="jg-card-save" method="POST" action="{{ route('customer.wishlist.store',$product) }}">@csrf<button aria-label="Save {{ $product->title }}"><i data-lucide="heart"></i></button></form>@else<a class="jg-card-save" href="{{ route('customer.login') }}" aria-label="Sign in to save {{ $product->title }}"><i data-lucide="heart"></i></a>@endif
                    <a class="photo" href="{{ route('storefront.products.show',$product->handle) }}"><img src="{{ $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->images->first()?->alt ?: $product->title }}" loading="lazy"></a>
                    <h2><a href="{{ route('storefront.products.show',$product->handle) }}">{{ $product->title }}</a></h2>
                    @if(filled($product->subtitle))<p class="jg-card-subtitle">{{ $product->subtitle }}</p>@endif<div class="productbottom"><span class="price">@productPrice($product, $variant?->price ?? $product->base_price)</span>@if($variant && $product->isBuyMode())<button type="button" class="details" data-quick-add-trigger data-quick-add-product-id="{{ $product->id }}" data-quick-add-variant-id="{{ $variant->id }}">Quick buy</button>@elseif(!$product->isBuyMode())<a class="details" href="{{ route('storefront.products.show',$product->handle) }}">View details</a>@endif</div>
                </article>
            @empty
                <div class="empty">No products match your selection. <a href="{{ $resetUrl }}">Reset filters</a></div>
            @endforelse
            </div>
            @if($products->hasPages())<div class="pagination">{{ $products->links() }}</div>@else<div class="end">@if($products->total())You’ve viewed all {{ $products->total() }} {{ $products->total()===1?'product':'products' }}@endif</div>@endif
        </main></div>
        <div class="drawerBackdrop" x-cloak x-show="filtersOpen" @click="filtersOpen=false"></div>
        <aside class="drawer" x-cloak x-show="filtersOpen" x-trap.inert.noscroll="filtersOpen" role="dialog" aria-modal="true" aria-labelledby="jewelry-filter-title"><header><h2 id="jewelry-filter-title">Filters</h2><button type="button" class="close" @click="filtersOpen=false" aria-label="Close filters">×</button></header><div class="jg-filter-body"><x-storefront.jewelry-filters :mobile="true" :locked="(bool)($collection||$listingCategory)" :categories="$categories" :search="$search" :category="$category" :sort="$sort" :layout="$layout" :filters="$filters" :facets="$facets" :route-name="$listingRouteName" :route-parameters="$listingRouteParameters" /></div><div class="drawerBottom"><a href="{{ $resetUrl }}">Reset filters</a><button type="submit" form="jewelry-filter-form" class="apply">Show results</button></div></aside>
        @foreach($products as $product)@if($product->variants->isNotEmpty())<x-storefront.quick-add-variant-modal :product="$product" :variants="$product->variants" :images="$product->images" theme="jewelry" />@endif@endforeach
        <x-storefront.footer />
    </div>
</x-layouts.app>
