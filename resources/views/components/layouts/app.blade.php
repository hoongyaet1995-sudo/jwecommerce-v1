@props(['title' => null, 'description' => null])
<!doctype html>
@php($isCmsStorefrontPreview = request()->routeIs('admin.pages.preview', 'admin.cms-canary.pages.show'))
@php($isStorefront = request()->routeIs('storefront.*', 'cart.*', 'checkout.*', 'customer.*', 'order-tracking.*') || $isCmsStorefrontPreview)
@php($isAdmin = request()->routeIs('admin.*') && ! $isCmsStorefrontPreview)
<html lang="en" @if($isStorefront) class="dark" data-atelier-theme-root data-atelier-theme="dark" @elseif($isAdmin) data-admin-root @endif>
<head>
    <x-brand-favicon />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?: $shopBrandName }}</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <livewire:styles />
    {{ $head ?? '' }}
</head>
<body>
    {{ $slot }}
    @if($isStorefront)
        <x-storefront.support-chat />
        <div id="cart-drawer" class="pointer-events-none fixed inset-0 z-[60] overflow-hidden" aria-hidden="true">
            <div data-cart-drawer-backdrop class="absolute inset-0 bg-black/70 opacity-0 backdrop-blur-sm transition-opacity duration-300"></div>
            <aside data-cart-drawer-panel role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title" class="absolute inset-y-0 right-0 flex w-full max-w-md translate-x-full flex-col border-l border-zinc-200 bg-white shadow-2xl transition-transform duration-300 dark:border-zinc-800 dark:bg-zinc-900">
                @include('storefront.partials.cart-drawer', app(\App\Services\CartViewData::class)->for(request()))
            </aside>
        </div>
    @endif
    <livewire:scripts />
</body>
</html>
