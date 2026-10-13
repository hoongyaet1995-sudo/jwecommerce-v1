@php($story = $product->pdpContent?->story ?? [])
@if(collect(['heading','description','image'])->contains(fn($key)=>filled($story[$key]??null)))
<section data-commerce-product-zone="story" @class(['product-story'=>true,'product-story--text'=>empty($story['image']),'product-story--image'=>empty($story['heading'])&&empty($story['description'])])>
    @if(filled($story['heading']??null)||filled($story['description']??null))<div>
        @if(filled($story['label']??null))<p class="eyebrow">{{ $story['label'] }}</p>@endif
        @if(filled($story['heading']??null))<h2>{{ $story['heading'] }}</h2>@endif
        @if(filled($story['description']??null))<p class="story-description">{{ $story['description'] }}</p>@endif
    </div>@endif
    @if(filled($story['image']??null))<img src="{{ $story['image'] }}" alt="{{ $story['alt']??'' }}" loading="lazy" style="object-position:{{ $story['focus']??'center' }} {{ ($story['focusY']??'middle')==='middle'?'center':$story['focusY'] }}">@endif
</section>
@endif
