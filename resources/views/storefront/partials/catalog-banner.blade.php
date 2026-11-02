<picture class="catalog-banner-picture" style="display:contents" data-catalog-banner>
 @if(filled($media['mobile']))<source media="(max-width:760px)" srcset="{{ $media['mobile'] }}">@endif
 <img class="{{ $imageClass ?? '' }}" src="{{ $media['desktop'] }}" alt="{{ $media['alt'] }}" style="object-position:{{ $media['focus'] }} {{ $media['focusY']==='middle'?'center':$media['focusY'] }}">
</picture>
