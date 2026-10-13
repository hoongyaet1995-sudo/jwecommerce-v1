@php
 $textMode=$media['textMode']??'default';
 $textColor=match($textMode){'light'=>'#ffffff','dark'=>'#17121a','custom'=>$media['textColor']??'#ffffff',default=>null};
 $overlay=$media['overlay']??'none';
 $alpha=number_format(($media['overlayStrength']??40)/100,2,'.','');
 $overlayColor=$overlay==='light'?'255,255,255':'0,0,0';
@endphp
@if($textColor||$overlay!=='none')
<style data-banner-readability>
@if($textColor)
[data-all-products-hero] :is(.heroCopy,.food-hero-copy) :is(h1,p){color:{{ $textColor }}!important}
@endif
.food-theme [data-all-products-hero].food-hero--image .food-hero-copy{background:transparent!important}
[data-all-products-hero] .banner-readability-overlay{position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba({{ $overlayColor }},{{ $alpha }}) 0%,rgba({{ $overlayColor }},{{ $alpha }}) 25%,transparent 85%);z-index:0}
</style>
@if($overlay!=='none')<div class="banner-readability-overlay" aria-hidden="true"></div>@endif
@endif
