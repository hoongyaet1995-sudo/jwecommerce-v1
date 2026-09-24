<x-layouts.app :title="$collection->title.' | '.$shopBrandName">
    <x-storefront.nav active="plp" :pdp-url="$products->first() ? route('storefront.products.show', ['handle' => $products->first()->handle]) : null" />
    <main class="source-collection-page">
        <header class="source-collection-hero">
            <div class="source-container"><p>{{ $collection->badge ?: 'CURATED COLLECTION' }}</p><h1>{{ $collection->title }}</h1><span>{{ $collection->subtitle ?: 'A considered edit of objects for intentional work.' }}</span><small>Collection Lookbook · {{ $products->count() }} pieces</small></div>
        </header>
        <section class="source-container source-collection-products">
            @forelse($products as $product)
                @php($defaultVariant = $product->variants->first())
                <article class="source-plp-card">
                    <a href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}" class="source-plp-image"><img src="{{ $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->images->first()?->alt ?: $product->title }}"></a>
                    <div class="source-plp-copy"><small>{{ $product->brand ?: 'JW ATELIER' }}</small><a href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}"><h2>{{ $product->title }}</h2></a><p>{{ app(\App\Services\ProductDescriptionHtml::class)->excerpt($product->description, $product->subtitle) }}</p></div>
                    <div class="source-plp-buy"><strong>@storefrontMoney($product->base_price)</strong>@if($defaultVariant && app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($defaultVariant) > 0)<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}"><input type="hidden" name="quantity" value="1"><button aria-label="Add {{ $product->title }} to bag">+</button></form>@endif</div>
                </article>
            @empty
                <div class="source-empty-results"><p>This collection is being assembled.</p><a href="{{ route('storefront.index') }}">Browse the full catalog</a></div>
            @endforelse
        </section>
    </main>
</x-layouts.app>
