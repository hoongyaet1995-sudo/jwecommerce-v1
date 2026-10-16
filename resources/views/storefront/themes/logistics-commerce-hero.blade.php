<?php $commerceMedia=app(\App\Services\WebsiteThemeCustomization::class)->media('logistics',$commerceSurface,asset('images/themes/logistics/hero.png')); ?>
<section class="lgc-commerce-hero {{ $commerceMedia['hidden']?'lgc-no-media':'' }}" data-commerce-page-media>
@if(!$commerceMedia['hidden'])@include('storefront.partials.theme-media',['media'=>$commerceMedia])@endif
<x-storefront.content-frame><div class="lgc-commerce-hero-copy">@if($commerceSurface==='cart')<p>Solutions for a stronger tomorrow</p>@endif<h1>{{ $commerceHeading }}</h1>@if(filled($commerceIntro??null))<p>{{ $commerceIntro }}</p>@endif<span aria-hidden="true"></span></div></x-storefront.content-frame>
</section>
