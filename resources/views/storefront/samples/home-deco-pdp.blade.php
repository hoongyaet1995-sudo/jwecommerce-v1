<x-layouts.app :title="$product->title">
<link rel="stylesheet" href="{{ asset('css/home-deco-pdp-sample.css') }}">
@php
 $available = $selectedVariant ? app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($selectedVariant) : null;
 $canAdd = app(\App\Services\StorefrontVariantData::class)->canAdd($selectedVariant);
 $price = $selectedVariant?->price ?? $product->base_price;
 $description = app(\App\Services\ProductDescriptionHtml::class)->clean($product->description);
@endphp
<div class="deco-pdp" data-commerce-product-anatomy="home-deco">
<div class="sample-strip"><span>Home & Decor / PDP design sample</span><span>Real product / shared purchase flow</span></div>
<x-storefront.nav active="pdp" />
<main>
<nav class="crumb"><a href="{{ route('storefront.index') }}">Shop</a> / @if($product->category)<a href="{{ route('storefront.categories.show', $product->category->slug) }}">{{ $product->category->name }}</a> / @endif {{ $product->title }}</nav>
<div class="product-overview">
<section class="gallery" aria-label="Product gallery">
<div class="hero"><img data-pdp-hero-image src="{{ $selectedImage?->path ?: asset('images/image-not-found.png') }}" alt="{{ $selectedImage?->alt ?: $product->title }}"></div>
<div class="thumbnails">@foreach($images as $image)<button type="button" data-pdp-thumbnail-switch data-pdp-image-path="{{ $image->path }}" data-pdp-image-alt="{{ $image->alt ?: $product->title }}" aria-label="View product image {{ $loop->iteration }}"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}"></button>@endforeach</div>
</section>
<section class="purchase" aria-label="Product purchase">
<p class="brand">{{ $pdpBrand }}</p><h1>{{ $product->title }}</h1>
@if(filled($product->subtitle))<p class="subtitle">{{ $product->subtitle }}</p>@endif
<div class="price"><strong data-pdp-price>@storefrontMoney($price)</strong><del data-pdp-compare-at class="hidden"></del><span data-pdp-save class="hidden"></span></div>
<p class="sku">SKU <span data-pdp-sku>{{ $selectedVariant?->sku }}</span></p>
<form method="POST" action="{{ route('cart.items.store') }}">@csrf
<input type="hidden" data-pdp-variant-input name="variant_id" value="{{ $selectedVariant?->id }}">
<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" />
<label class="quantity">Quantity <input aria-label="Quantity" name="quantity" type="number" min="1" max="{{ max(1, $available ?? 1) }}" value="1"></label>
<button class="add" data-pdp-add-to-cart @disabled(!$canAdd)>{{ $canAdd ? 'Add to bag' : ($available === null ? 'Awaiting stock verification' : 'Unavailable') }}</button>
<button class="buy" data-pdp-instant-buy name="checkout" value="1" @disabled(!$canAdd)>Buy now</button>
</form>
@if($pdpPaymentMethods)<p class="payment">Payment at checkout / {{ implode(' / ', $pdpPaymentMethods) }}</p>@endif
</section>
</div>
<section class="information">
@if(filled($description))<details open><summary>About this piece</summary><div class="description">{!! $description !!}</div></details>@endif
@if($product->pdpContent?->specifications)<details><summary>Details & dimensions</summary>@foreach($product->pdpContent->specifications as $spec)@if(is_array($spec))<p>{{ $spec['label'] ?? '' }} {{ $spec['value'] ?? '' }}</p>@endif @endforeach</details>@endif
@foreach($product->pdpContent?->custom_accordions ?? [] as $item)@if(filled($item['title'] ?? null) && filled($item['content'] ?? null))<details><summary>{{ $item['title'] }}</summary><p>{{ strip_tags($item['content']) }}</p></details>@endif @endforeach
</section>
@if($companions->isNotEmpty())<section class="related"><h2>Pair it with</h2><div>@foreach($companions as $item)<a href="{{ url('/page-template-sample/home-deco-pdp/'.$item->handle) }}"><img src="{{ $item->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $item->title }}"><h3>{{ $item->title }}</h3></a>@endforeach</div></section>@endif
</main><x-storefront.footer />
</div></x-layouts.app>
