@props(['design','presentation'=>null,'header'=>false])
@php
    $normalLogo=app(\App\Support\ShopBrand::class)->configuredLogo();
    $variants=app(\App\Services\StorefrontShellSettingsService::class)->live()['overlayLogos'] ?? [];
    $solidLogo=($design['appearance']==='dark' ? ($variants['light']??null) : ($variants['dark']??null)) ?: $normalLogo;
    $overlayLogo=$header && ($presentation['mode']??'normal')!=='normal' ? (($variants[$presentation['tone']]??null) ?: $solidLogo) : $solidLogo;
@endphp
@if(request()->attributes->has('websiteThemeDemoVariants'))
    <span class="jw-theme-demo-brand">JW STUDIO<small>{{ request('theme')==='food'?'FOOD & BEVERAGE':'CONSIDERED EVERYDAY' }}</small></span>
@elseif($solidLogo)
    <img class="jw-shell-brand-logo" src="{{ $solidLogo }}" alt="{{ $shopBrandName }}" @if($header) data-shell-brand-logo data-solid-logo="{{ $solidLogo }}" data-overlay-logo="{{ $overlayLogo }}" @endif>
@else
    {{ $shopBrandName }}
@endif
