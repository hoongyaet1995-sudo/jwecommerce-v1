<x-layouts.app :title="$offer->name">
<x-storefront.nav />
<main style="max-width:1280px;margin:auto;padding:2rem 5% 5rem;color:inherit;font:inherit" data-mix-match-offer>
<nav aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a> / {{ $offer->name }}</nav>
<header style="padding:3rem 0;border-bottom:1px solid currentColor;margin-bottom:2rem">
<p>Mix &amp; Match</p><h1 style="font-size:clamp(2rem,5vw,3.5rem);line-height:1.1;margin:1rem 0">{{ $offer->name }}</h1>
<p>Pick any {{ $offer->quantity }} participating items for <strong>@storefrontMoney($offer->price)</strong>.</p>
@if($offer->description)<p>{{ $offer->description }}</p>@endif
<p>Same or different items. {{ $offer->repeat ? 'Multiple combinations allowed.' : 'One combination per order.' }} Extra items keep their regular price. Applies only when it saves you money.</p>
<a href="{{ route('cart.show') }}">View your bag →</a></header>
<form method="GET" style="margin:1rem 0"><label>Search products <input name="search" value="{{ request('search') }}" style="color:inherit;background:transparent;border:1px solid;padding:.6rem"></label><button style="border:1px solid;padding:.6rem 1rem">Search</button></form>
<div class="grid grid-cols-2 gap-6 lg:grid-cols-4">@forelse($products as $product)<x-storefront.product-card-grid :product="$product" />@empty<p>No participating products available.</p>@endforelse</div>
<div style="margin-top:2rem">{{ $products->links() }}</div></main>
<x-storefront.footer />
</x-layouts.app>
