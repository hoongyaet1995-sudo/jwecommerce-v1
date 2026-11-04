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
<div class="wd-theme" data-wedding-pdp data-commerce-product-anatomy="wedding">
<x-storefront.nav active="pdp"/>
<main class="wd-pdp">
<x-storefront.content-frame><nav class="wd-crumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a><span>/</span><a href="{{ route('storefront.index') }}">Photography</a>@if($product->category)<span>/</span><a href="{{ route('storefront.categories.show',$product->category->slug) }}">{{ $product->category->name }}</a>@endif<span>/</span><span>{{ $product->title }}</span></nav></x-storefront.content-frame>
<section class="wd-pdp-hero" data-commerce-product-zone="media" x-data="{zoom:false}" @keydown.escape.window="zoom=false">
@if($images->first())<img data-pdp-hero-image src="{{ $images->first()->path }}" alt="{{ $images->first()->alt ?: $product->title }}" fetchpriority="high">@endif
<x-storefront.content-frame><button class="wd-gallery-open" type="button" @click="zoom=true">View gallery ↗</button></x-storefront.content-frame>
<div class="wd-gallery-dialog" x-show="zoom" x-cloak x-trap.inert.noscroll="zoom" role="dialog" aria-modal="true" aria-label="Product photographs"><button type="button" @click="zoom=false" aria-label="Close gallery">×</button><div>@foreach($images as $image)<img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" loading="lazy">@endforeach</div></div>
</section>
<x-storefront.content-frame><section class="wd-pdp-intro"><div>@if($product->category)<p class="wd-eyebrow">{{ $product->category->name }}</p>@endif<h1>{{ $product->title }}</h1>@if(filled($product->subtitle))<p class="wd-lead">{{ $product->subtitle }}</p>@endif<div class="wd-description">{!! $description !!}</div></div><aside data-enquiry-context class="wd-pdp-enquiry" data-commerce-product-zone="purchase">@include('storefront.themes.wedding-purchase')
@include('storefront.partials.mix-match-product')
@include('storefront.partials.addon-product')
@include('storefront.partials.free-gift-product')
</aside></section></x-storefront.content-frame>
@if($images->count()>1)<section class="wd-pdp-marquee" aria-label="More product photographs" x-data="{paused:false}"><x-storefront.content-frame><header><p class="wd-eyebrow">A closer look</p><button type="button" @click="paused=!paused" class="wd-marquee-toggle" :aria-pressed="paused" :aria-label="paused?'Play photographs':'Pause photographs'" aria-label="Pause photographs"><svg x-show="!paused" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="14" y="3" width="5" height="18" rx="1"/><rect x="5" y="3" width="5" height="18" rx="1"/></svg><svg x-show="paused" x-cloak class="wd-marquee-play" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg></button></header></x-storefront.content-frame><div class="wd-marquee" :class="{'is-paused':paused}"><div class="wd-marquee-track">@foreach([false,true] as $duplicate)<div class="wd-marquee-group" @if($duplicate) aria-hidden="true" @endif>@foreach($images->skip(1) as $image)<img src="{{ $image->path }}" alt="{{ $duplicate?'':($image->alt ?: $product->title) }}" loading="lazy">@endforeach</div>@endforeach</div></div></section>@endif
<x-storefront.content-frame>
<section class="wd-service-details"><div><h2>The details,<br>with room for you.</h2><p>Every celebration is different. Tell us what matters most, and we’ll shape the collection together.</p></div><div>
@if($highlights->isNotEmpty())<details class="wd-detail" open><summary>Highlights</summary><ul>@foreach($highlights as $item)<li>{{ is_array($item)?$item['text']:$item }}</li>@endforeach</ul></details>@endif
@if($specs->isNotEmpty())<details class="wd-detail"><summary>Specifications</summary><dl>@foreach($specs as $spec)<div><dt>{{ $spec['label']??'' }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl></details>@endif
@foreach($content?->custom_accordions??[] as $accordion)@if(filled($accordion['title']??null)&&filled($accordion['content']??null))<details class="wd-detail"><summary>{{ $accordion['title'] }}</summary><div>{!! app(\App\Services\ProductDescriptionHtml::class)->clean($accordion['content']) !!}</div></details>@endif@endforeach
@if(filled($shipping['policyTitle']??null))<details class="wd-detail"><summary>{{ $shipping['policyTitle'] }}</summary><div>{!! app(\App\Services\ProductDescriptionHtml::class)->clean($shipping['policyContentHtml']??'') !!}</div></details>@endif
</div></section>
@php($story=$content?->story??[])
@if(collect(['heading','description','image'])->contains(fn($key)=>filled($story[$key]??null)))<section class="wd-product-story" data-commerce-product-zone="story">@if(filled($story['image']??null))<img src="{{ $story['image'] }}" alt="{{ $story['alt']??'' }}" style="object-position:{{ $story['focus']??'center' }} {{ ($story['focusY']??'middle')==='middle'?'center':$story['focusY'] }}">@endif<div>@if(filled($story['label']??null))<p class="wd-eyebrow">{{ $story['label'] }}</p>@endif<h2>{{ $story['heading']??'' }}</h2><p>{{ $story['description']??'' }}</p></div></section>@endif
@if($companions->isNotEmpty())<section class="wd-related" data-commerce-product-zone="related"><p class="wd-eyebrow">Related collections</p><h2>More ways to tell your story.</h2><div>@foreach($companions as $item)<a href="{{ route('storefront.products.show',$item->handle) }}">@if($item->images->first())<img src="{{ $item->images->first()->path }}" alt="{{ $item->title }}" loading="lazy">@endif<h3>{{ $item->title }}</h3><span>View collection →</span></a>@endforeach</div></section>@endif
@if($reviews->isNotEmpty())<section class="wd-reviews"><h2>Customer reviews</h2>@foreach($reviews as $review)<article><span>{{ $review->rating }} / 5</span><h3>{{ $review->title }}</h3><p>{{ $review->comment }}</p></article>@endforeach</section>@endif
</x-storefront.content-frame></main><x-storefront.footer/></div></x-layouts.app>
