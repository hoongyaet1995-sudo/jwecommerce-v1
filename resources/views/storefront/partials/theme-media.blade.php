<picture>
    @if($media['mobile'])<source media="(max-width:760px)" srcset="{{ $media['mobile'] }}">@endif
    <img src="{{ $media['desktop'] }}" alt="{{ $media['alt'] }}" style="object-position:{{ $media['focus'] }} {{ ($media['focusY']??'middle')==='middle'?'center':$media['focusY'] }}" fetchpriority="high">
</picture>
