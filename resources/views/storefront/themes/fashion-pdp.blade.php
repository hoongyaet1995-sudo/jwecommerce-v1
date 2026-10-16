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


<div class="fs-theme" data-fashion-pdp data-commerce-product-anatomy="fashion">

<x-storefront.nav active="pdp" :pdp-url="$productUrl" />

<main class="fs-pdp-main">

<nav class="fs-crumb" aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a> / @if($product->category)<a href="{{ route('storefront.categories.show',$product->category->slug) }}">{{ $product->category->name }}</a> / @endif {{ $product->title }}</nav>

<div class="fs-pdp-overview">

<section class="fs-gallery" aria-label="Product gallery" data-commerce-product-zone="media" x-data="{zoom:false}" @keydown.escape.window="zoom=false">

<div class="fs-gallery-grid">
<button type="button" class="fs-gallery-main" @click="zoom=true" aria-label="Enlarge product image"><img data-pdp-hero-image src="{{ $selectedVariant?->image_url ?: $selectedImage?->path ?: asset('images/image-not-found.png') }}" alt="{{ $selectedImage?->alt ?: $product->title }}" fetchpriority="high"></button>
@foreach($images->slice(1,1) as $image)<button type="button" @click="zoom=true;$nextTick(()=>document.querySelector('.fs-image-dialog img').src=@js($image->path))" aria-label="Enlarge detail image"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}"></button>@endforeach
</div>
@if($images->count()>2)<div class="fs-gallery-extra">@foreach($images->slice(2) as $image)<button type="button" @click="zoom=true;$nextTick(()=>document.querySelector('.fs-image-dialog img').src=@js($image->path))" aria-label="Enlarge additional image"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}"></button>@endforeach</div>@endif
<div x-show="zoom" x-cloak class="fs-image-dialog" role="dialog" aria-modal="true" aria-label="Enlarged product image" @click.self="zoom=false"><button type="button" @click="zoom=false" aria-label="Close enlarged image">&times;</button><img :src="document.querySelector('[data-pdp-hero-image]').src" alt="{{ $product->title }}"></div>


</section>

<section class="fs-purchase" data-commerce-product-zone="purchase">

@if($product->category)<p class="fs-eyebrow">{{ $product->category->name }}</p>@endif

<div class="fs-title-row"><h1>{{ $product->title }}</h1></div>

@if(filled($product->subtitle))<p class="fs-muted fs-subtitle">{{ $product->subtitle }}</p>@endif

@if($product->showsPrice())<div class="fs-price" data-purchase-mode="{{ $product->purchase_mode }}"><strong data-pdp-price>@productPrice($product, $price)</strong><del data-pdp-compare-at @class(['hidden'=>$compare<=$price])>@if($compare>$price)@storefrontMoney($compare)@endif</del><span data-pdp-save @class(['hidden'=>$compare<=$price])>@if($compare>$price)Save @storefrontMoney($compare-$price)@endif</span></div>@endif

<p class="fs-fine fs-sku">SKU <span data-pdp-sku>{{ $selectedVariant?->sku }}</span></p>

@if($reviews->isNotEmpty())<a class="fs-rating" href="#fs-reviews">{{ number_format($reviews->avg('rating'),1) }} / 5 · {{ $reviews->count() }} reviews</a>@endif

@if($product->isBuyMode())<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}">

<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" presentation="baby">

<div class="fs-quantity" x-data="{quantity:1}"><label for="fs-quantity">Quantity</label><div class="tq-stepper"><button type="button" @click="quantity=Math.max(1,quantity-1)" aria-label="Decrease quantity">−</button><input id="fs-quantity" name="quantity" type="number" x-model.number="quantity" value="1" min="1" max="{{ max(1,$available ?? 1) }}"><button type="button" @click="quantity=Math.min(Number(document.getElementById('fs-quantity').max),quantity+1)" aria-label="Increase quantity">+</button></div></div>
</x-storefront.variant-selector>

<button data-pdp-add-to-cart class="fs-primary" @disabled(!$canAdd)>{{ $canAdd?'Add to cart':($available===null?'Awaiting stock verification':'Unavailable') }} &rarr;</button>

<button data-pdp-instant-buy class="fs-outline fs-buy" name="checkout" value="1" @disabled(!$canAdd)>Buy now</button>

</form>@else @include('storefront.partials.product-enquiry-actions', ['actionClass'=>'fs-primary', 'optionPresentation'=>'baby']) @endif
<div class="fs-save-item">@if($preview)<button disabled><i data-lucide="heart"></i>Save item</button>@elseif($customer)<form method="POST" action="{{ route('customer.wishlist.store',$product) }}">@csrf<button><i data-lucide="heart"></i>Save item</button></form>@else<a href="{{ route('customer.login') }}"><i data-lucide="heart"></i>Save item</a>@endif</div>

@if($highlights->isNotEmpty())<ul class="fs-highlights">@foreach($highlights as $item)<li>@if(is_array($item) && filled($item['icon'] ?? null))<i data-lucide="{{ \App\Services\ProductHighlightIcons::resolve($item['icon']) }}" style="width:18px;height:18px;display:inline-block;vertical-align:middle;margin-right:8px" aria-hidden="true"></i>@endif@if(is_array($item)&&filled($item['badge']??null))<strong>{{ $item['badge'] }}</strong>@endif<small>{{ is_array($item)?$item['text']:$item }}</small></li>@endforeach</ul>@endif

@if(filled($shipping['shippingBadgeText']??null))<p class="fs-fine">{{ $shipping['shippingBadgeText'] }}</p>@endif
@if(filled($shipping['leadTimeText']??null))<p class="fs-fine">{{ $shipping['leadTimeText'] }}</p>@endif
@if(filled($shipping['policyTitle']??null))<details class="fs-detail"><summary>{{ $shipping['policyTitle'] }}</summary><div class="fs-description">{!! app(\App\Services\ProductDescriptionHtml::class)->clean($shipping['policyContentHtml'] ?? '') !!}</div></details>@endif

@if($product->isBuyMode() && $pdpPaymentMethods)<p class="fs-fine">Payment at checkout · {{ implode(' / ',$pdpPaymentMethods) }}</p>@endif

<section class="fs-finer"><div class="fs-care-card">
@if(filled($description))<details class="fs-detail"><summary>Product details<i data-lucide="chevron-down"></i></summary><div>{!! $description !!}</div></details>@endif
@if($specs->isNotEmpty())<details open class="fs-detail"><summary>Specifications<i data-lucide="chevron-down"></i></summary><dl>@foreach($specs as $spec)<div><dt>{{ $spec['label']??'' }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl></details>@endif
@foreach($content?->custom_accordions??[] as $accordion)@if(filled($accordion['title']??null)&&filled($accordion['content']??null))<details class="fs-detail"><summary>{{ $accordion['title'] }}<i data-lucide="chevron-down"></i></summary><div>{!! app(\App\Services\ProductDescriptionHtml::class)->clean($accordion['content']) !!}</div></details>@endif@endforeach
</div></section></section></div>

<section class="fs-fashion-story">@include('storefront.themes.fashion-product-story')</section>

@if($companions->isNotEmpty())<section class="fs-related" data-commerce-product-zone="related"><h2>You may also like</h2><div>@foreach($companions as $item)<a href="{{ route('storefront.products.show',$item->handle) }}"><img src="{{ $item->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $item->title }}" loading="lazy"><h3>{{ $item->title }}</h3><p>@productPrice($item, $item->variants->first()?->price ?? $item->base_price)</p></a>@endforeach</div></section>@endif

@if($reviews->isNotEmpty())<section id="fs-reviews" class="fs-reviews"><h2>Customer reviews</h2>@foreach($reviews as $review)<article><p>{{ $review->rating }} / 5</p><h3>{{ $review->title }}</h3><p>{{ $review->comment }}</p></article>@endforeach</section>@endif

</main><x-storefront.footer /></div></x-layouts.app>
