@props(['design','presentation'=>null,'header'=>false])
@php
    $displayBrand=trim((string)($design['brandName']??''));
    $shopBrandName=$displayBrand?:$shopBrandName;
    $hasDisplayLogo=filled($design['brandLogo']??'');
    $normalLogo=$hasDisplayLogo?$design['brandLogo']:app(\App\Support\ShopBrand::class)->configuredLogo();
    $variants=$hasDisplayLogo?[]:(app(\App\Services\StorefrontShellSettingsService::class)->live()['overlayLogos'] ?? []);
    $solidLogo=($variants['dark']??null) ?: $normalLogo;
    $overlayLogo=$header && ($presentation['mode']??'normal')!=='normal' ? (($variants[$presentation['tone']]??null) ?: $solidLogo) : $solidLogo;
@endphp
@if(request()->attributes->has('websiteThemeDemoVariants'))
@if(request()->attributes->get('websiteDesignPreview')==='logistics')
    <span class="jw-logistics-demo-brand">JTG<span aria-hidden="true"></span><small>CONTAINERS &amp; LOGISTICS</small></span>
@else
    <span class="jw-theme-demo-brand">JW STUDIO<small>{{ request('theme')==='food'?'FOOD & BEVERAGE':'CONSIDERED EVERYDAY' }}</small></span>
@endif
@elseif($solidLogo)
    <img class="jw-shell-brand-logo" src="{{ $solidLogo }}" alt="{{ $shopBrandName }}" @if($header) data-shell-brand-logo data-solid-logo="{{ $solidLogo }}" data-overlay-logo="{{ $overlayLogo }}" @endif>
@else
    {{ $shopBrandName }}
@endif
