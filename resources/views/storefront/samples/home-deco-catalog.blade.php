{!! str_replace('</html>', '', file_get_contents(public_path('page-template-sample/home-deco-catalog.html'))) !!}
@foreach($products as $product)
    <x-storefront.quick-add-variant-modal :product="$product" :variants="$product->variants" :images="$product->images" />
@endforeach
<div id="cart-drawer" class="pointer-events-none fixed inset-0 z-[60] overflow-hidden" aria-hidden="true">
    <div data-cart-drawer-backdrop class="absolute inset-0 bg-black/70 opacity-0 backdrop-blur-sm transition-opacity duration-300"></div>
    <aside data-cart-drawer-panel role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title" class="absolute inset-y-0 right-0 flex w-full max-w-md translate-x-full flex-col border-l border-zinc-200 bg-white shadow-2xl transition-transform duration-300 dark:border-zinc-800 dark:bg-zinc-900">
        @include('storefront.partials.cart-drawer', app(\App\Services\CartViewData::class)->for(request()))
    </aside>
</div>
@php($sampleCurrency = app(\App\Support\StorefrontCurrencyContext::class)->current())
<template data-atelier-currency-options data-atelier-currency-symbol-left="{{ $sampleCurrency['symbolLeft'] }}" data-atelier-currency-symbol-right="{{ $sampleCurrency['symbolRight'] }}" data-atelier-currency-decimals="{{ $sampleCurrency['decimalPlaces'] }}" data-atelier-currency-rate="{{ $sampleCurrency['exchangeRate'] }}"></template>
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>.details{color:#62704e}.siteheader .logo{color:#282b22}.drawer h2{font-size:27px}body{font-family:Arial,sans-serif}</style>
<link rel="stylesheet" href="/page-template-sample/home-deco-quick-buy.css">
<script>
document.querySelectorAll('[data-quick-add-modal]').forEach(modal=>{
 const full=modal.querySelector('[data-quick-add-product-link] span');if(full)full.textContent='Product details';
 const overlay=modal.querySelector('[data-quick-add-sold-out-overlay]');
 if(overlay){const variants=JSON.parse(modal.dataset.quickAddVariants||'[]');const pending=variants[0]?.stock==null;const title=overlay.querySelector('span');if(title)title.textContent=pending?'Availability pending':'Currently unavailable';const detail=overlay.querySelector('span + span');if(detail)detail.textContent='See availability for your selected option.';}
});
</script>
</html>

