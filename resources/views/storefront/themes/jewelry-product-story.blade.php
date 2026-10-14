@php($story=$product->pdpContent?->story??[])
@if(collect(['heading','description','image'])->contains(fn($key)=>filled($story[$key]??null)))
<section class="product-story" data-commerce-product-zone="story">
@if(filled($story['image']??null))<img src="{{ $story['image'] }}" alt="{{ $story['alt']??'' }}" loading="lazy" style="object-position:{{ $story['focus']??'center' }} {{ ($story['focusY']??'middle')==='middle'?'center':$story['focusY'] }}">@endif
<div class="product-story-copy">@if(filled($story['label']??null))<p class="jg-eyebrow">{{ $story['label'] }}</p>@endif @if(filled($story['heading']??null))<h2>{{ $story['heading'] }}</h2>@endif @if(filled($story['description']??null))<p>{{ $story['description'] }}</p>@endif</div>
</section>@endif
