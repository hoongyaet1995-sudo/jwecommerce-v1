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

<x-slot:head><link rel="stylesheet" href="{{ asset('css/baby-theme.css') }}?v={{ filemtime(public_path('css/baby-theme.css')) }}"></x-slot:head>
<div class="bk-theme" data-baby-pdp data-commerce-product-anatomy="baby">

<x-storefront.nav active="pdp" :pdp-url="$productUrl" />

<main class="bk-pdp-main">

<nav class="bk-crumb" aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a> / @if($product->category)<a href="{{ route('storefront.categories.show',$product->category->slug) }}">{{ $product->category->name }}</a> / @endif {{ $product->title }}</nav>

<div class="bk-pdp-overview">

<section class="bk-gallery" aria-label="Product gallery" data-commerce-product-zone="media" x-data="{zoom:false}" @keydown.escape.window="zoom=false">

@if($images->count()>1)<div class="bk-gallery-rail">@foreach($images as $index=>$image)<button data-pdp-thumbnail data-pdp-thumbnail-switch data-pdp-image-path="{{ $image->path }}" data-pdp-image-alt="{{ $image->alt ?: $product->title }}" type="button" aria-label="View image {{ $index+1 }}"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" loading="lazy"></button>@endforeach</div>@endif
<button type="button" class="bk-gallery-main" @click="zoom=true" aria-label="Enlarge product image"><img data-pdp-hero-image src="{{ $selectedVariant?->image_url ?: $selectedImage?->path ?: asset('images/image-not-found.png') }}" alt="{{ $selectedImage?->alt ?: $product->title }}" fetchpriority="high"><span class="bk-enlarge">View larger &nearr;</span></button>



@if($images->count()>2)<div class="bk-gallery-details">@foreach($images->slice(1,2) as $image)<img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" loading="lazy">@endforeach</div>@endif
<div x-show="zoom" x-cloak class="bk-image-dialog" role="dialog" aria-modal="true" aria-label="Enlarged product image" @click.self="zoom=false"><button type="button" @click="zoom=false" aria-label="Close enlarged image">&times;</button><img :src="document.querySelector('[data-pdp-hero-image]').src" alt="{{ $product->title }}"></div>

@if($specs->isNotEmpty())<section class="bk-key-specs"><h3>Key specifications</h3><dl>@foreach($specs->take(4) as $spec)<div><dt>{{ $spec['label']??'' }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl></section>@endif
</section>

<section class="bk-purchase" data-commerce-product-zone="purchase">

@if($product->category)<p class="bk-eyebrow">{{ $product->category->name }}</p>@endif

<div class="bk-title-row"><h1>{{ $product->title }}</h1>@if(!$preview)<form method="POST" action="{{ $customer ? route('customer.wishlist.store',$product) : route('customer.login') }}">@csrf @if($customer)<button type="submit" aria-label="Save to wishlist"><i data-lucide="heart" class="w-5 h-5"></i></button>@else<a href="{{ route('customer.login') }}" aria-label="Sign in to save"><i data-lucide="heart" class="w-5 h-5"></i></a>@endif</form>@endif</div>

@if(filled($product->subtitle))<p class="bk-muted bk-subtitle">{{ $product->subtitle }}</p>@endif

@if($product->showsPrice())<div class="bk-price"><strong data-pdp-price>@productPrice($product, $price)</strong><del data-pdp-compare-at @class(['hidden'=>$compare<=$price])>@if($compare>$price)@storefrontMoney($compare)@endif</del><span data-pdp-save @class(['hidden'=>$compare<=$price])>@if($compare>$price)Save @storefrontMoney($compare-$price)@endif</span></div>@endif

<p class="bk-fine bk-sku">SKU <span data-pdp-sku>{{ $selectedVariant?->sku }}</span></p>

@if($reviews->isNotEmpty())<a class="bk-rating" href="#bk-reviews">{{ number_format($reviews->avg('rating'),1) }} / 5 · {{ $reviews->count() }} reviews</a>@endif

@if($product->isBuyMode())<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}">

<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" presentation="baby">

<div class="bk-quantity" x-data="{quantity:1}"><label for="bk-quantity">Quantity</label><div class="tq-stepper"><button type="button" @click="quantity=Math.max(1,quantity-1)" aria-label="Decrease quantity">−</button><input id="bk-quantity" name="quantity" type="number" x-model.number="quantity" value="1" min="1" max="{{ max(1,$available ?? 1) }}"><button type="button" @click="quantity=Math.min(Number(document.getElementById('bk-quantity').max),quantity+1)" aria-label="Increase quantity">+</button></div></div>
</x-storefront.variant-selector>

<button data-pdp-add-to-cart class="bk-primary" @disabled(!$canAdd)>{{ $canAdd?'Add to cart':($available===null?'Awaiting stock verification':'Unavailable') }} &rarr;</button>

<button data-pdp-instant-buy class="bk-outline bk-buy" name="checkout" value="1" @disabled(!$canAdd)>Buy now</button>

</form>@else @include('storefront.partials.product-enquiry-actions', ['actionClass'=>'bk-primary', 'optionPresentation'=>'baby']) @endif

@if($highlights->isNotEmpty())<ul class="bk-highlights">@foreach($highlights as $item)<li>@if(is_array($item) && filled($item['icon'] ?? null))<i data-lucide="{{ \App\Services\ProductHighlightIcons::resolve($item['icon']) }}" style="width:18px;height:18px;display:inline-block;vertical-align:middle;margin-right:8px" aria-hidden="true"></i>@endif{{ is_array($item)?$item['text']:$item }}</li>@endforeach</ul>@endif

@if(filled($shipping['shippingBadgeText']??null))<p class="bk-fine">{{ $shipping['shippingBadgeText'] }}</p>@endif
@if(filled($shipping['leadTimeText']??null))<p class="bk-fine">{{ $shipping['leadTimeText'] }}</p>@endif
@if(filled($shipping['policyTitle']??null))<details class="bk-detail"><summary>{{ $shipping['policyTitle'] }}</summary><div class="bk-description">{!! app(\App\Services\ProductDescriptionHtml::class)->clean($shipping['policyContentHtml'] ?? '') !!}</div></details>@endif

@if($product->isBuyMode() && $pdpPaymentMethods)<p class="bk-fine">Payment at checkout · {{ implode(' / ',$pdpPaymentMethods) }}</p>@endif

</section></div>

<section class="bk-product-details"><div class="bk-product-detail-grid"><div class="bk-care-card"><h2>Details &amp; care</h2>@if(filled($description))<details open class="bk-detail"><summary>Details<i data-lucide="chevron-down" aria-hidden="true"></i></summary><div class="bk-description">{!! $description !!}</div></details>@endif

@if($specs->isNotEmpty())<details class="bk-detail"><summary>Specifications<i data-lucide="chevron-down" aria-hidden="true"></i></summary><dl>@foreach($specs as $spec)<div><dt>{{ $spec['label'] ?? $spec['name'] ?? '' }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl></details>@endif

@foreach($content?->custom_accordions ?? [] as $accordion)@if(filled($accordion['title']??null) && filled($accordion['content']??null))<details class="bk-detail"><summary>{{ $accordion['title'] }}<i data-lucide="chevron-down" aria-hidden="true"></i></summary><div class="bk-description">{!! app(\App\Services\ProductDescriptionHtml::class)->clean($accordion['content']) !!}</div></details>@endif @endforeach

</div><div class="bk-story-wrapper">@include('storefront.partials.product-story')@if(collect(['heading','description','image'])->contains(fn($key)=>filled($content?->story[$key]??null)))<svg class="bk-story-ornament" viewBox="0 0 90 82" aria-hidden="true"><path d="M40 36c-8 0-12-5-12-11s4-11 10-11c1-9 7-14 15-14s14 6 15 13c10 0 17 6 17 15 0 6-5 10-13 10H40ZM16 44l4 9 10 1-7 7 2 10-9-5-9 5 2-10-7-7 10-1Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>@endif</div></div></section>
@if($companions->isNotEmpty())<section class="bk-related" data-commerce-product-zone="related"><h2>You may also like</h2><div>@foreach($companions as $item)<a href="{{ route('storefront.products.show',$item->handle) }}"><img src="{{ $item->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $item->title }}" loading="lazy"><h3>{{ $item->title }}</h3><p>@productPrice($item, $item->variants->first()?->price ?? $item->base_price)</p></a>@endforeach</div></section>@endif

@if($reviews->isNotEmpty())<section id="bk-reviews" class="bk-reviews"><h2>Customer reviews</h2>@foreach($reviews as $review)<article><p>{{ $review->rating }} / 5</p><h3>{{ $review->title }}</h3><p>{{ $review->comment }}</p></article>@endforeach</section>@endif

</main><x-storefront.footer /></div></x-layouts.app>
