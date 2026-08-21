<x-layouts.app :title="$collection->title.' | JW Atelier'">
    <x-storefront.nav active="plp" :pdp-url="$products->first() ? route('storefront.products.show', $products->first()) : null" />
    <main class="source-collection-page">
        <header class="source-collection-hero">
            <div class="source-container"><p>{{ $collection->badge ?: 'CURATED COLLECTION' }}</p><h1>{{ $collection->title }}</h1><span>{{ $collection->subtitle ?: 'A considered edit of objects for intentional work.' }}</span><small>Collection Lookbook · {{ $products->count() }} pieces</small></div>
        </header>
        <section class="source-container source-collection-products">
            @forelse($products as $product)
                @php($defaultVariant = $product->variants->first())
                <article class="source-plp-card">
                    <a href="{{ route('storefront.products.show', $product) }}" class="source-plp-image">@if($product->images->first())<img src="{{ $product->images->first()->path }}" alt="{{ $product->images->first()->alt }}">@endif</a>
                    <div class="source-plp-copy"><small>{{ $product->brand ?: 'JW ATELIER' }}</small><a href="{{ route('storefront.products.show', $product) }}"><h2>{{ $product->title }}</h2></a><p>{{ $product->subtitle ?: 'Considered object for intentional work.' }}</p></div>
                    <div class="source-plp-buy"><strong>${{ number_format($product->base_price / 100, 2) }}</strong>@if($defaultVariant && $defaultVariant->stock > 0)<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}"><input type="hidden" name="quantity" value="1"><button aria-label="Add {{ $product->title }} to bag">+</button></form>@endif</div>
                </article>
            @empty
                <div class="source-empty-results"><p>This collection is being assembled.</p><a href="{{ route('storefront.index') }}">Browse the full catalog</a></div>
            @endforelse
        </section>
    </main>
</x-layouts.app>
