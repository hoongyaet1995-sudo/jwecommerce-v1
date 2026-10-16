@php
    $mediaTheme = request()->attributes->get('websiteRenderedTheme') ?? app(\App\Services\WebsiteDesignService::class)->surface();
    $checkoutMediaDefaults = [
        'food'=>['images/themes/food/tracking.png','fd-checkout-media'],
        'home_deco'=>['images/cms-samples/home-shop-living.png','hd-checkout-media'],
        'beauty'=>['images/themes/beauty/ritual-campaign.png','bs-checkout-media'],
        'tech'=>['images/themes/tech/headphones.png','tco-art'],
    ];
    $checkoutMediaSlot = $checkoutMediaDefaults[$mediaTheme] ?? null;
    $checkoutMedia = $checkoutMediaSlot ? app(\App\Services\WebsiteThemeCustomization::class)->media($mediaTheme,'checkout',asset($checkoutMediaSlot[0])) : null;
@endphp
@if($checkoutMedia && !$checkoutMedia['hidden'])
<div class="{{ $checkoutMediaSlot[1] }}" data-checkout-page-media>@include('storefront.partials.theme-media',['media'=>$checkoutMedia])</div>
@endif
