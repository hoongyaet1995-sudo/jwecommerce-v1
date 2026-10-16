@php
    $listingTitle=$collection?->title ?? $listingCategory?->name ?? ($catalogListingSettings['title'] ?: 'All products');
    $description=$collection?->subtitle ?: ($listingCategory?->description ?: ($listingCategory ? '' : $catalogListingSettings['description']));
    $heroImage=$collection?->cover_image ?: $listingCategory?->image_url;
    $isAllProducts=!$collection&&!$listingCategory;
    $allProductsMedia=$isAllProducts?app(\App\Services\WebsiteThemeCustomization::class)->media('home_deco','allProducts',''):null;
    if($isAllProducts)$heroImage=$allProductsMedia['hidden']?'':$allProductsMedia['desktop'];
    $resetUrl=route($listingRouteName,$listingRouteParameters);
    $activeCount=($filters['priceRequested']?1:0)+count($filters['colors'])+count($filters['materials'])+($filters['inStock']?1:0)+($filters['minRating']>0?1:0);
@endphp
<x-layouts.app :title="$listingTitle.' | '.$shopBrandName">
    <style>.all-products--compact{min-height:0!important;height:auto!important;display:block!important;padding:28px 0!important;margin-bottom:0!important}.all-products--compact .heroCopy,.all-products--compact .food-hero-copy{width:100%!important;max-width:none!important;padding:0!important;position:static!important}.all-products--compact h1{font-size:clamp(32px,4vw,52px)!important;max-width:none!important;width:100%!important}.home-deco-theme .all-products-picture{min-width:0}.home-deco-theme .all-products-picture img{width:100%;height:320px;object-fit:cover}.beauty-theme .all-products-picture,.food-theme .all-products-picture{position:absolute;inset:0;overflow:hidden}.beauty-theme .all-products-picture img,.food-theme .all-products-picture img{width:100%;height:100%;object-fit:cover}@media(max-width:760px){.home-deco-theme .all-products-picture img{height:240px}.all-products--compact{padding:20px 0!important}}</style>
    <link rel="stylesheet" href="{{ asset('css/home-deco-catalog.css') }}?v={{ filemtime(public_path('css/home-deco-catalog.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/home-deco-quick-buy.css') }}?v={{ filemtime(public_path('css/home-deco-quick-buy.css')) }}">
    <div class="home-deco-theme" data-website-theme="home_deco" data-commerce-collection-anatomy x-data="{filtersOpen:false}" @keydown.escape.window="filtersOpen=false">
        <x-storefront.nav active="plp" />
        <div class="frame"><main class="container">
            <div class="breadcrumbs"><a href="/">Home</a><span>/</span><a href="{{ route('storefront.index') }}">Shop</a>@if($collection || $listingCategory)<span>/</span><span>{{ $listingTitle }}</span>@endif</div>
            <section @class(['hero'=>true,'all-products--compact'=>$isAllProducts&&!filled($heroImage)])><div><h1>{{ $listingTitle }}</h1>@if(filled($description))<p>{{ strip_tags($description) }}</p>@endif</div>@if(filled($heroImage))@if($isAllProducts)<div class="all-products-picture" data-all-products-media>@include('storefront.partials.theme-media',['media'=>$allProductsMedia,'imageClass'=>'heroPhoto'])</div>@else<img class="heroPhoto" src="{{ $heroImage }}" alt="{{ $listingTitle }}">@endif@endif</section>
            <div class="catalogbar"><span>{{ $products->total() }} {{ $products->total()===1?'product':'products' }}</span><div class="barActions"><button type="button" @click="filtersOpen=true" aria-label="Open filters"><i data-lucide="sliders-horizontal" class="icon"></i>Filters @if($activeCount)({{ $activeCount }})@endif</button><form method="GET" action="{{ $resetUrl }}">
                @foreach(array_intersect_key($listingRouteParameters,array_flip(['theme','scope','customization'])) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                @foreach(request()->except(['sort','page','theme','scope']) as $key=>$value)
                    @if(is_array($value))@foreach($value as $entry)<input type="hidden" name="{{ $key }}[]" value="{{ $entry }}">@endforeach
                    @elseif(filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <select name="sort" aria-label="Sort products" onchange="this.form.submit()"><option value="">Featured</option><option value="price_asc" @selected($sort==='price_asc')>Price: low to high</option><option value="price_desc" @selected($sort==='price_desc')>Price: high to low</option><option value="newest" @selected($sort==='newest')>Newest</option>@if($facets['hasRatings'])<option value="rating" @selected($sort==='rating')>Customer rating</option>@endif</select>
            </form></div></div>
            <div class="products" data-commerce-product-grid-layout="editorial-grid">
            @forelse($products as $product)
                @php($variant=$product->variants->first())
                <article class="product" data-storefront-product-card>
                    <a class="photo" href="{{ route('storefront.products.show',$product->handle) }}"><img src="{{ $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->images->first()?->alt ?: $product->title }}" loading="lazy"></a>
                    @if(filled($product->brand))<p class="brand">{{ $product->brand }}</p>@endif
                    <h2><a href="{{ route('storefront.products.show',$product->handle) }}">{{ $product->title }}</a></h2>
                    <div class="productbottom"><span class="price">@productPrice($product, $variant?->price ?? $product->base_price)</span>@if($variant && $product->isBuyMode())<button type="button" class="details" data-quick-add-trigger data-quick-add-product-id="{{ $product->id }}" data-quick-add-variant-id="{{ $variant->id }}">Buy Now</button>@elseif(!$product->isBuyMode())<a class="details" href="{{ route('storefront.products.show',$product->handle) }}">View details</a>@endif</div>
                </article>
            @empty
                <div class="empty">No products match your selection. <a href="{{ $resetUrl }}">Reset filters</a></div>
            @endforelse
            </div>
            @if($products->hasPages())<div class="pagination">{{ $products->links() }}</div>@else<div class="end">@if($products->total())You’ve viewed all {{ $products->total() }} {{ $products->total()===1?'product':'products' }}@endif</div>@endif
        </main></div>
        <div class="drawerBackdrop" x-cloak x-show="filtersOpen" @click="filtersOpen=false"></div>
        <aside class="drawer" x-cloak x-show="filtersOpen" x-trap.inert.noscroll="filtersOpen" role="dialog" aria-modal="true" aria-labelledby="home-filter-title"><header><h2 id="home-filter-title">Filters</h2><button type="button" class="close" @click="filtersOpen=false" aria-label="Close filters">×</button></header><div class="hd-filter-body"><x-storefront.home-deco-filters :mobile="true" :categories="$categories" :search="$search" :category="$category" :sort="$sort" :layout="$layout" :filters="$filters" :facets="$facets" :route-name="$listingRouteName" :route-parameters="$listingRouteParameters" /></div><div class="drawerBottom"><a href="{{ $resetUrl }}">Reset filters</a><button type="button" class="apply" @click="filtersOpen=false">Done</button></div></aside>
        @foreach($products as $product)@if($product->variants->isNotEmpty())<x-storefront.quick-add-variant-modal :product="$product" :variants="$product->variants" :images="$product->images" theme="home_deco" />@endif@endforeach
        <x-storefront.footer />
    </div>
</x-layouts.app>
