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

<x-slot:head><link rel="stylesheet" href="{{ asset('css/jewelry-theme.css') }}?v={{ filemtime(public_path('css/jewelry-theme.css')) }}"></x-slot:head>
<div class="jg-theme" data-jewelry-pdp data-commerce-product-anatomy="jewelry">

<x-storefront.nav active="pdp" :pdp-url="$productUrl" />

<main class="jg-pdp-main">

<nav class="jg-crumb" aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a> / @if($product->category)<a href="{{ route('storefront.categories.show',$product->category->slug) }}">{{ $product->category->name }}</a> / @endif {{ $product->title }}</nav>

<div class="jg-pdp-overview">

<section class="jg-gallery" aria-label="Product gallery" data-commerce-product-zone="media" x-data="{zoom:false}" @keydown.escape.window="zoom=false">

<div class="jg-gallery-grid">
<button type="button" class="jg-gallery-main" @click="zoom=true" aria-label="Enlarge product image"><img data-pdp-hero-image src="{{ $selectedVariant?->image_url ?: $selectedImage?->path ?: asset('images/image-not-found.png') }}" alt="{{ $selectedImage?->alt ?: $product->title }}" fetchpriority="high"></button>
@foreach($images->slice(1,2) as $image)<button type="button" @click="zoom=true;$nextTick(()=>document.querySelector('.jg-image-dialog img').src=@js($image->path))" aria-label="Enlarge detail image"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}"></button>@endforeach
</div>
@if($images->count()>3)<div class="jg-gallery-extra">@foreach($images->slice(3) as $image)<button type="button" @click="zoom=true;$nextTick(()=>document.querySelector('.jg-image-dialog img').src=@js($image->path))" aria-label="Enlarge additional image"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}"></button>@endforeach</div>@endif
<div x-show="zoom" x-cloak class="jg-image-dialog" role="dialog" aria-modal="true" aria-label="Enlarged product image" @click.self="zoom=false"><button type="button" @click="zoom=false" aria-label="Close enlarged image">&times;</button><img :src="document.querySelector('[data-pdp-hero-image]').src" alt="{{ $product->title }}"></div>


</section>

<section class="jg-purchase" data-commerce-product-zone="purchase">

@if($product->category)<p class="jg-eyebrow">{{ $product->category->name }}</p>@endif

<div class="jg-title-row"><h1>{{ $product->title }}</h1></div>

@if(filled($product->subtitle))<p class="jg-muted jg-subtitle">{{ $product->subtitle }}</p>@endif

@if($product->showsPrice())<div class="jg-price"><strong data-pdp-price>@productPrice($product, $price)</strong><del data-pdp-compare-at @class(['hidden'=>$compare<=$price])>@if($compare>$price)@storefrontMoney($compare)@endif</del><span data-pdp-save @class(['hidden'=>$compare<=$price])>@if($compare>$price)Save @storefrontMoney($compare-$price)@endif</span></div>@endif

<p class="jg-fine jg-sku">SKU <span data-pdp-sku>{{ $selectedVariant?->sku }}</span></p>

@if($reviews->isNotEmpty())<a class="jg-rating" href="#jg-reviews">{{ number_format($reviews->avg('rating'),1) }} / 5 · {{ $reviews->count() }} reviews</a>@endif


@if($product->isBuyMode())<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}">

<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" presentation="baby">

<div class="jg-quantity" x-data="{quantity:1}"><label for="jg-quantity">Quantity</label><div class="tq-stepper"><button type="button" @click="quantity=Math.max(1,quantity-1)" aria-label="Decrease quantity">−</button><input id="jg-quantity" name="quantity" type="number" x-model.number="quantity" value="1" min="1" max="{{ max(1,$available ?? 1) }}"><button type="button" @click="quantity=Math.min(Number(document.getElementById('jg-quantity').max),quantity+1)" aria-label="Increase quantity">+</button></div></div>
</x-storefront.variant-selector>

<button data-pdp-add-to-cart class="jg-primary" @disabled(!$canAdd)>{{ $canAdd?'Add to cart':($available===null?'Awaiting stock verification':'Unavailable') }} &rarr;</button>

<button data-pdp-instant-buy class="jg-outline jg-buy" name="checkout" value="1" @disabled(!$canAdd)>Buy now</button>

</form>
@include('storefront.partials.mix-match-product')
@include('storefront.partials.addon-product')
@else @include('storefront.partials.product-enquiry-actions', ['actionClass'=>'jg-primary', 'optionPresentation'=>'baby']) @endif
<div class="jg-save-item">@if($preview)<button disabled><i data-lucide="heart"></i>Save item</button>@elseif($customer)<form method="POST" action="{{ route('customer.wishlist.store',$product) }}">@csrf<button><i data-lucide="heart"></i>Save item</button></form>@else<a href="{{ route('customer.login') }}"><i data-lucide="heart"></i>Save item</a>@endif</div>

@if($highlights->isNotEmpty())<ul class="jg-highlights">@foreach($highlights as $item)<li>@if(is_array($item) && filled($item['icon'] ?? null))<i data-lucide="{{ \App\Services\ProductHighlightIcons::resolve($item['icon']) }}" style="width:18px;height:18px;display:inline-block;vertical-align:middle;margin-right:8px" aria-hidden="true"></i>@endif@if(is_array($item)&&filled($item['badge']??null))<strong>{{ $item['badge'] }}</strong>@endif<small>{{ is_array($item)?$item['text']:$item }}</small></li>@endforeach</ul>@endif

@if(filled($shipping['shippingBadgeText']??null))<p class="jg-fine">{{ $shipping['shippingBadgeText'] }}</p>@endif
@if(filled($shipping['leadTimeText']??null))<p class="jg-fine">{{ $shipping['leadTimeText'] }}</p>@endif
@if(filled($shipping['policyTitle']??null))<details class="jg-detail"><summary>{{ $shipping['policyTitle'] }}</summary><div class="jg-description">{!! app(\App\Services\ProductDescriptionHtml::class)->clean($shipping['policyContentHtml'] ?? '') !!}</div></details>@endif

@if($product->isBuyMode() && $pdpPaymentMethods)<p class="jg-fine">Payment at checkout · {{ implode(' / ',$pdpPaymentMethods) }}</p>@endif

</section></div>

<section class="jg-jewelry-story">@include('storefront.themes.jewelry-product-story')</section>
<section class="jg-finer"><h2>The finer details</h2><div class="jg-care-card">
@if(filled($description))<details class="jg-detail"><summary>Product details<i data-lucide="chevron-down"></i></summary><div>{!! $description !!}</div></details>@endif
@if($specs->isNotEmpty())<details open class="jg-detail"><summary>Specifications<i data-lucide="chevron-down"></i></summary><dl>@foreach($specs as $spec)<div><dt>{{ $spec['label']??'' }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl></details>@endif
@foreach($content?->custom_accordions??[] as $accordion)@if(filled($accordion['title']??null)&&filled($accordion['content']??null))<details class="jg-detail"><summary>{{ $accordion['title'] }}<i data-lucide="chevron-down"></i></summary><div>{!! app(\App\Services\ProductDescriptionHtml::class)->clean($accordion['content']) !!}</div></details>@endif@endforeach
</div></section>
@if($companions->isNotEmpty())<section class="jg-related" data-commerce-product-zone="related"><h2>You may also like</h2><div>@foreach($companions as $item)<a href="{{ route('storefront.products.show',$item->handle) }}"><img src="{{ $item->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $item->title }}" loading="lazy"><h3>{{ $item->title }}</h3><p>@productPrice($item, $item->variants->first()?->price ?? $item->base_price)</p></a>@endforeach</div></section>@endif

@if($reviews->isNotEmpty())<section id="jg-reviews" class="jg-reviews"><h2>Customer reviews</h2>@foreach($reviews as $review)<article><p>{{ $review->rating }} / 5</p><h3>{{ $review->title }}</h3><p>{{ $review->comment }}</p></article>@endforeach</section>@endif

</main><x-storefront.footer /></div></x-layouts.app>
