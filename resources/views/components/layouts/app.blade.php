@props(['title' => null, 'description' => null, 'shellDesign' => null, 'shellContentWidth' => null])
<!doctype html>
@php($isCmsStorefrontPreview = request()->routeIs('admin.pages.preview', 'admin.cms-canary.pages.show', 'admin.shell-design.preview', 'admin.website-design.preview', 'admin.website-design.acceptance.sample'))
@php($isStorefront = request()->routeIs('storefront.*', 'cart.*', 'checkout.*', 'customer.*', 'order-tracking.*') || $isCmsStorefrontPreview)
@php($isAdmin = request()->routeIs('admin.*') && ! $isCmsStorefrontPreview)
@php($shellDesign = $isStorefront ? ($shellDesign ?? \App\Domain\Cms\ShellDesign::resolve(app(\App\Services\StorefrontShellSettingsService::class)->live())) : null)
@php($shellContentWidth = $isStorefront ? ($shellContentWidth ?? app(\App\Services\StorefrontShellSettingsService::class)->live()['contentWidth'] ?? 'wide') : 'wide')
<html lang="en" @if($isStorefront) data-storefront-width="{{ $shellContentWidth }}" @endif @if($isStorefront) class="{{ ($shellDesign['appearance'] ?? 'dark') === 'dark' ? 'dark' : '' }}" data-atelier-theme-root data-atelier-theme="{{ $shellDesign['appearance'] ?? 'dark' }}" @if($shellDesign) data-shell-appearance="{{ $shellDesign['appearance'] }}" data-shell-theme-switch="{{ $shellDesign['showThemeSwitch'] ? 'true' : 'false' }}" @endif @elseif($isAdmin) data-admin-root @endif>
<head>
    @if(request()->routeIs('admin.website-design.preview')) @include('admin.theme-demo-guard',['scenario'=>request('surface','plp')]) @endif
    <x-brand-favicon />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?: $shopBrandName }}</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <livewire:styles />
    @if($isStorefront)
        <link rel="stylesheet" href="{{ asset('css/storefront-layout.css') }}?v={{ filemtime(public_path('css/storefront-layout.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/storefront-scrollbars.css') }}?v={{ filemtime(public_path('css/storefront-scrollbars.css')) }}">
    @endif
    @if($isStorefront && app(\App\Services\WebsiteDesignService::class)->surface() === 'home_deco')<link rel="stylesheet" href="{{ asset('css/home-deco-theme.css') }}?v={{ filemtime(public_path('css/home-deco-theme.css')) }}">@endif
    @if($isStorefront && app(\App\Services\WebsiteDesignService::class)->surface() === 'beauty')<link rel="stylesheet" href="{{ asset('css/beauty-theme.css') }}?v={{ filemtime(public_path('css/beauty-theme.css')) }}">@endif
    @if($isStorefront && app(\App\Services\WebsiteDesignService::class)->surface() === 'beauty')<link rel="stylesheet" href="{{ asset('css/beauty-complete.css') }}?v={{ filemtime(public_path('css/beauty-complete.css')) }}">@endif
    @if($isStorefront && app(\App\Services\WebsiteDesignService::class)->surface()==='food')<link rel="stylesheet" href="{{ asset('css/food-theme.css') }}?v={{ filemtime(public_path('css/food-theme.css')) }}">@endif
    {{ $head ?? '' }}
</head>
<body>
    {{ $slot }}
    @if($isStorefront)
        @unless(request()->attributes->has('websiteThemeDemoVariants'))<x-storefront.support-chat />@endunless
        <div id="cart-drawer" data-cart-drawer-url="{{ request()->attributes->has('websiteThemeDemoVariants') ? route('admin.website-design.cart-drawer', ['theme'=>request('theme'),'customization'=>request('customization')]) : route('cart.drawer') }}" class="pointer-events-none fixed inset-0 z-[60] overflow-hidden" aria-hidden="true">
            <div data-cart-drawer-backdrop class="absolute inset-0 bg-black/70 opacity-0 backdrop-blur-sm transition-opacity duration-300"></div>
            <aside data-cart-drawer-panel role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title" class="absolute inset-y-0 right-0 flex w-full max-w-md translate-x-full flex-col border-l border-zinc-200 bg-white shadow-2xl transition-transform duration-300 dark:border-zinc-800 dark:bg-zinc-900">
                @if(request()->attributes->has('websiteThemeDemoVariants'))
                    @include('storefront.partials.cart-drawer', app(\App\Services\WebsiteThemeDemoData::class)->cart(app(\App\Services\WebsiteThemeDemoData::class)->catalog(request())))
                @else
                    @include('storefront.partials.cart-drawer', app(\App\Services\CartViewData::class)->for(request()))
                @endif
            </aside>
        </div>
    @endif
    @if(request()->routeIs('storefront.index','storefront.products.show','storefront.categories.show','storefront.collections.show','customer.*','admin.website-design.preview'))
        @include('storefront.partials.theme-customization')
    @endif
    <livewire:scripts />
</body>
</html>
