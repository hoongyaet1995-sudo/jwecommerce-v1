@php

 $preview=(bool)($websiteDesignPreview ?? false);

 $available=$selectedVariant ? app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($selectedVariant) : null;

 $canAdd=app(\App\Services\StorefrontVariantData::class)->canAdd($selectedVariant, $product);

 $price=$selectedVariant?->price ?? $product->base_price;

 $compare=$product->isBuyMode() ? ($selectedVariant ? $selectedVariant->compare_at_price : $product->compare_at_price) : 0;

 $description=app(\App\Services\ProductDescriptionHtml::class)->clean($product->description);

 $content=$product->pdpContent;

 $specs=collect($content?->specifications ?? [])->filter(fn($s)=>is_array($s)&&filled($s['value']??null));

 $highlights=collect($content?->highlights ?? [])->filter(fn($s)=>filled(is_array($s)?($s['text']??null):$s));

 $shipping=$content && !$content->use_store_default_shipping ? ($content->shipping_policy ?? []) : [];

 $reviews=$product->reviews;

 $customer=auth('customer')->user()?->customer;

 $productUrl=route('storefront.products.show',$product->handle);

@endphp

<x-layouts.app :title="$product->title.' | '.$pdpBrand" :description="$product->seo_description">

<x-slot:head><link rel="stylesheet" href="{{ asset('css/food-theme.css') }}?v={{ filemtime(public_path('css/food-theme.css')) }}"></x-slot:head>
<div class="fd-theme" data-food-pdp data-commerce-product-anatomy="food">

<x-storefront.nav active="pdp" :pdp-url="$productUrl" />

<main class="fd-pdp-main">

<nav class="fd-crumb" aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a> / @if($product->category)<a href="{{ route('storefront.categories.show',$product->category->slug) }}">{{ $product->category->name }}</a> / @endif {{ $product->title }}</nav>

<div class="fd-pdp-overview">

<section class="fd-gallery" aria-label="Product gallery" data-commerce-product-zone="media" x-data="{zoom:false}" @keydown.escape.window="zoom=false">

<button type="button" class="fd-gallery-main" @click="zoom=true" aria-label="Enlarge product image"><img data-pdp-hero-image src="{{ $selectedVariant?->image_url ?: $selectedImage?->path ?: asset('images/image-not-found.png') }}" alt="{{ $selectedImage?->alt ?: $product->title }}" fetchpriority="high"><span class="fd-enlarge">View larger &nearr;</span></button>

@if($images->count()>1)<div class="fd-gallery-grid">@foreach($images as $index=>$image)<button data-pdp-thumbnail data-pdp-thumbnail-switch data-pdp-image-path="{{ $image->path }}" data-pdp-image-alt="{{ $image->alt ?: $product->title }}" type="button" aria-label="View image {{ $index+1 }}"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" loading="lazy"></button>@endforeach</div>@endif

<div x-show="zoom" x-cloak class="fd-image-dialog" role="dialog" aria-modal="true" aria-label="Enlarged product image" @click.self="zoom=false"><button type="button" @click="zoom=false" aria-label="Close enlarged image">&times;</button><img :src="document.querySelector('[data-pdp-hero-image]').src" alt="{{ $product->title }}"></div>

</section>

<section class="fd-purchase" data-commerce-product-zone="purchase">

@if($product->category)<p class="fd-eyebrow">{{ $product->category->name }}</p>@endif

<div class="fd-title-row"><h1>{{ $product->title }}</h1>@if(!$preview)<form method="POST" action="{{ $customer ? route('customer.wishlist.store',$product) : route('customer.login') }}">@csrf @if($customer)<button type="submit" aria-label="Save to wishlist"><i data-lucide="heart" class="w-5 h-5"></i></button>@else<a href="{{ route('customer.login') }}" aria-label="Sign in to save"><i data-lucide="heart" class="w-5 h-5"></i></a>@endif</form>@endif</div>

@if(filled($product->subtitle))<p class="fd-muted fd-subtitle">{{ $product->subtitle }}</p>@endif

@if($product->showsPrice())<div class="fd-price"><strong data-pdp-price>@productPrice($product, $price)</strong><del data-pdp-compare-at @class(['hidden'=>$compare<=$price])>@if($compare>$price)@storefrontMoney($compare)@endif</del><span data-pdp-save @class(['hidden'=>$compare<=$price])>@if($compare>$price)Save @storefrontMoney($compare-$price)@endif</span></div>@endif

@if(filled($pdpBrand))<p class="fd-fine">{{ $pdpBrand }}</p>@endif

@if($reviews->isNotEmpty())<a class="fd-rating" href="#fd-reviews">{{ number_format($reviews->avg('rating'),1) }} / 5 · {{ $reviews->count() }} reviews</a>@endif


@if($product->isBuyMode())<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}">

<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" />

<div class="fd-quantity" x-data><label for="fd-quantity">Quantity</label><div class="fd-stepper"><button type="button" aria-label="Decrease quantity" @click="$refs.quantity.stepDown()">&minus;</button><input x-ref="quantity" id="fd-quantity" name="quantity" type="number" value="1" min="1" max="{{ max(1,$available ?? 1) }}"><button type="button" aria-label="Increase quantity" @click="$refs.quantity.stepUp()">+</button></div></div>

<button data-pdp-add-to-cart class="fd-primary" @disabled(!$canAdd)>{{ $canAdd?'Add to bag':($available===null?'Awaiting stock verification':'Unavailable') }} &rarr;</button>

<button data-pdp-instant-buy class="fd-outline fd-buy" name="checkout" value="1" @disabled(!$canAdd)>Buy now</button>

</form>
@include('storefront.partials.mix-match-product')
@include('storefront.partials.addon-product')
@include('storefront.partials.free-gift-product')
@else @include('storefront.partials.product-enquiry-actions', ['actionClass'=>'fd-primary']) @endif

<p class="fd-fine">SKU <span data-pdp-sku>{{ $selectedVariant?->sku }}</span></p>

@if($highlights->isNotEmpty())<ul class="fd-highlights">@foreach($highlights as $item)<li>@if(is_array($item) && filled($item['icon'] ?? null))<i data-lucide="{{ \App\Services\ProductHighlightIcons::resolve($item['icon']) }}" style="width:18px;height:18px;display:inline-block;vertical-align:middle;margin-right:8px" aria-hidden="true"></i>@endif{{ is_array($item)?$item['text']:$item }}</li>@endforeach</ul>@endif

@if(filled($description))<details class="fd-detail"><summary>Details</summary><div class="fd-description">{!! $description !!}</div></details>@endif

@if($specs->isNotEmpty())<details class="fd-detail"><summary>Specifications</summary><dl>@foreach($specs as $spec)<div><dt>{{ $spec['label'] ?? $spec['name'] ?? '' }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl></details>@endif

@foreach($content?->custom_accordions ?? [] as $accordion)@if(filled($accordion['title']??null) && filled($accordion['content']??null))<details class="fd-detail"><summary>{{ $accordion['title'] }}</summary><div class="fd-description">{!! app(\App\Services\ProductDescriptionHtml::class)->clean($accordion['content']) !!}</div></details>@endif @endforeach

@if(filled($shipping['shippingBadgeText']??null))<p class="fd-fine">{{ $shipping['shippingBadgeText'] }}</p>@endif
@if(filled($shipping['leadTimeText']??null))<p class="fd-fine">{{ $shipping['leadTimeText'] }}</p>@endif
@if(filled($shipping['policyTitle']??null))<details class="fd-detail"><summary>{{ $shipping['policyTitle'] }}</summary><div class="fd-description">{!! app(\App\Services\ProductDescriptionHtml::class)->clean($shipping['policyContentHtml'] ?? '') !!}</div></details>@endif

@if($product->isBuyMode() && $pdpPaymentMethods)<p class="fd-fine">Payment at checkout · {{ implode(' / ',$pdpPaymentMethods) }}</p>@endif

</section></div>

@include('storefront.partials.product-story')

@if($companions->isNotEmpty())<section class="fd-related" data-commerce-product-zone="related"><h2>You may also like</h2><div>@foreach($companions as $item)<a href="{{ route('storefront.products.show',$item->handle) }}"><img src="{{ $item->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $item->title }}" loading="lazy"><h3>{{ $item->title }}</h3><p>@productPrice($item, $item->variants->first()?->price ?? $item->base_price)</p></a>@endforeach</div></section>@endif

@if($reviews->isNotEmpty())<section id="fd-reviews" class="fd-reviews"><h2>Customer reviews</h2>@foreach($reviews as $review)<article><p>{{ $review->rating }} / 5</p><h3>{{ $review->title }}</h3><p>{{ $review->comment }}</p></article>@endforeach</section>@endif

</main><x-storefront.footer /></div></x-layouts.app>
