@props(['active' => 'landing', 'presentation' => ['mode'=>'normal','tone'=>'dark'], 'design' => null])

@php
    $hasSession = request()->hasSession();
    $cartCount = $hasSession ? collect(request()->session()->get('cart', []))->sum() : 0;
    $memberUser = $hasSession ? auth('customer')->user() : null;
    $initial = str($memberUser?->name ?: 'U')->substr(0, 1)->upper();
    $currencySettings = app(\App\Support\CurrencySettings::class);
    $currencies = $currencySettings->enabled();
    $selectedCurrency = $hasSession
        ? app(\App\Support\StorefrontCurrencyContext::class)->current()
        : $currencySettings->default();
    $header = \App\Models\Menu::query()->where('key', 'header')->with([
        'rootItems' => fn ($query) => $query->where('is_active', true),
        'rootItems.children' => fn ($query) => $query->where('is_active', true),
        'rootItems.children.children' => fn ($query) => $query->where('is_active', true),
    ])->first();
    $items = $header?->rootItems?->where('is_active', true) ?? collect();
    $menuItems = $items->values()->map(fn ($item) => [
        'id' => 'menu-'.$item->id,
        'label' => $item->label,
        'url' => $item->url,
        'badge' => $item->badge,
        'badgeType' => $item->badge_type,
        'badgeColor' => $item->badge_color,
        'direct' => $item->children->isEmpty(),
        'columns' => $item->children->values(),
        'promo' => filled($item->featured_title) && filled($item->featured_image_url) ? [
            'badge' => $item->badge,
            'title' => $item->featured_title,
            'description' => $item->featured_description,
            'price' => $item->featured_price,
            'imageUrl' => $item->featured_image_url,
            'ctaText' => $item->featured_cta_label,
            'ctaUrl' => $item->featured_cta_url ?: $item->url,
        ] : null,
    ]);
    $megaMenuDetails = $menuItems->where('direct', false)->keyBy('id');
    $badgeStyle = fn (?string $color) => \App\Support\MenuBadgePalette::inlineStyle($color);
    $storefrontShell = is_array($storefrontShell ?? null) ? $storefrontShell : [];
    $design = $design ?? \App\Domain\Cms\ShellDesign::resolve($storefrontShell);
    $shellEnabled = $design ? $design['showAnnouncement'] : (bool) data_get($storefrontShell, 'enabled', false);
    $announcementLeft = is_array(data_get($storefrontShell, 'zones.left')) ? data_get($storefrontShell, 'zones.left') : [];
    $announcementCenter = is_array(data_get($storefrontShell, 'zones.center')) ? data_get($storefrontShell, 'zones.center') : [];
    $announcementRight = is_array(data_get($storefrontShell, 'zones.right')) ? data_get($storefrontShell, 'zones.right') : [];
    $leftVisible = $shellEnabled
        && (bool) data_get($announcementLeft, 'enabled', false)
        && filled(data_get($announcementLeft, 'text'));
    $centerVisible = $shellEnabled
        && (bool) data_get($announcementCenter, 'enabled', false)
        && filled(data_get($announcementCenter, 'text'));
    $trackingVisible = $shellEnabled
        && (bool) data_get($announcementRight, 'enabled', false)
        && (bool) data_get($announcementRight, 'trackingEnabled', false)
        && filled(data_get($announcementRight, 'trackingLabel'))
        && filled(data_get($announcementRight, 'trackingUrl'));
    $whatsappVisible = $shellEnabled
        && (bool) data_get($announcementRight, 'enabled', false)
        && (bool) data_get($announcementRight, 'whatsappEnabled', false)
        && filled(data_get($announcementRight, 'whatsappLabel'))
        && filled(data_get($announcementRight, 'whatsappUrl'));
    $rightVisible = $shellEnabled
        && (bool) data_get($announcementRight, 'enabled', false)
        && (
            filled(data_get($announcementRight, 'helperText'))
            || $trackingVisible
            || $whatsappVisible
        );
    $announcementVisible = $leftVisible || $centerVisible || $rightVisible;
    $announcementTheme = (string) data_get($storefrontShell, 'theme', 'midnight-obsidian');
    $announcementThemePalette = match ($announcementTheme) {
        'aurora-indigo' => ['background' => '#312e81', 'text' => '#e0e7ff'],
        'emerald-logistics' => ['background' => '#064e3b', 'text' => '#a7f3d0'],
        'amber-flash' => ['background' => '#78350f', 'text' => '#fde68a'],
        'crimson-campaign' => ['background' => '#881337', 'text' => '#ffe4e6'],
        'custom' => [
            'background' => data_get($storefrontShell, 'customPalette.background'),
            'text' => data_get($storefrontShell, 'customPalette.text'),
        ],
        default => ['background' => '#0f172a', 'text' => '#f8fafc'],
    };
    $isAnnouncementHex = static fn (mixed $value): bool => is_string($value)
        && preg_match('/\\A#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\\z/', $value) === 1;
    $announcementBackground = $isAnnouncementHex($announcementThemePalette['background'])
        ? $announcementThemePalette['background']
        : '#0f172a';
    $announcementText = $isAnnouncementHex($announcementThemePalette['text'])
        ? $announcementThemePalette['text']
        : '#f8fafc';
    $announcementStyle = sprintf(
        '--storefront-announcement-background: %s; --storefront-announcement-text: %s; --storefront-announcement-border: rgba(255,255,255,0.1); --storefront-announcement-badge-background: rgba(255,255,255,0.1); background-color: var(--storefront-announcement-background); color: var(--storefront-announcement-text); border-color: var(--storefront-announcement-border);',
        $announcementBackground,
        $announcementText,
    );
    $sourceIcon = static function (string $name, string $class): string {
        if ($name !== 'chevron-right') {
            return sprintf('<i data-lucide="%s" class="%s"></i>', e($name), e($class));
        }

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right %s" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>', e($class));
    };
    if (request()->attributes->has('websiteThemeDemoVariants')) {
        $design=array_replace(\App\Domain\Cms\ShellDesign::defaults(),['showAccount'=>true,'tagline'=>'Good food. Brighter days.']);
        $announcementVisible=false; $cartCount=2; $memberUser=null;
        $menuItems=collect(['Shop','Collections'])->map(fn($label)=>['id'=>'demo-'.$label,'label'=>$label,'url'=>'#','direct'=>true,'columns'=>collect(),'promo'=>null]);
        if(request()->attributes->get('websiteDesignPreview')==='logistics') {
            $design=array_replace($design,['tagline'=>'Containers. Logistics. A more connected tomorrow.','ctaLabel'=>'Request a Quote','ctaUrl'=>'#']);
            $menuItems=collect(['Containers','Services','Our Projects','About Us','Contact'])->map(fn($label)=>['id'=>'demo-logistics-'.str($label)->slug(),'label'=>$label,'url'=>'#','direct'=>true,'columns'=>collect(),'promo'=>null]);
        }
    }
@endphp

@if($design)
    @include('components.storefront.designed-nav')
@else
<header
    data-mega-nav
    data-header-mode="{{ $presentation['mode'] }}" data-header-tone="{{ $presentation['tone'] }}"
    x-data="{ searchOpen: false, userDropdownOpen: false, mobileMenuOpen: false, activeMenuId: null, mobileExpandedLayer1: null, mobileExpandedLayer2: null }"
    @mouseleave="activeMenuId = null"
    @focusout="if (! $el.contains($event.relatedTarget)) activeMenuId = null"
    @keydown.escape.window="searchOpen = false; userDropdownOpen = false; mobileMenuOpen = false; activeMenuId = null"
    class="sticky top-0 z-40 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800/80 text-zinc-900 dark:text-zinc-100 transition-colors duration-200 font-sans"
>
    @if($announcementVisible)
        <div data-storefront-announcement-theme="{{ $announcementTheme }}" style="{{ $announcementStyle }}" class="bg-zinc-100 dark:bg-zinc-900/90 border-b border-zinc-200/80 dark:border-zinc-800/80 text-xs py-1.5 px-4 text-center text-zinc-600 dark:text-zinc-300 flex items-center justify-between font-sans">
            @if($leftVisible)
                @if(filled(data_get($announcementLeft, 'linkUrl')))
                    <a href="{{ data_get($announcementLeft, 'linkUrl') }}" style="color: var(--storefront-announcement-text);" class="hidden sm:flex items-center gap-2 text-emerald-600 dark:text-emerald-400 text-[11px] font-medium">
                        <span style="background-color: var(--storefront-announcement-text);" class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                        <span>{{ data_get($announcementLeft, 'text') }}</span>
                        @if(filled(data_get($announcementLeft, 'badge')))
                            <span>{{ data_get($announcementLeft, 'badge') }}</span>
                        @endif
                    </a>
                @else
                    <div style="color: var(--storefront-announcement-text);" class="hidden sm:flex items-center gap-2 text-emerald-600 dark:text-emerald-400 text-[11px] font-medium">
                        <span style="background-color: var(--storefront-announcement-text);" class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                        <span>{{ data_get($announcementLeft, 'text') }}</span>
                        @if(filled(data_get($announcementLeft, 'badge')))
                            <span>{{ data_get($announcementLeft, 'badge') }}</span>
                        @endif
                    </div>
                @endif
            @endif
            <div class="mx-auto text-xs tracking-wide">
                @if($centerVisible)
                    @if(filled(data_get($announcementCenter, 'linkUrl')))
                        <a href="{{ data_get($announcementCenter, 'linkUrl') }}">
                            <span style="color: var(--storefront-announcement-text);" class="font-medium text-amber-600 dark:text-amber-300">{{ data_get($announcementCenter, 'text') }}</span>
                            @if(filled(data_get($announcementCenter, 'couponCode')))
                                <span> • Code </span>
                                <span style="background-color: var(--storefront-announcement-badge-background); color: var(--storefront-announcement-text);" class="font-mono bg-zinc-200 dark:bg-zinc-700/80 px-1.5 py-0.5 rounded text-zinc-900 dark:text-zinc-100 font-semibold">{{ data_get($announcementCenter, 'couponCode') }}</span>
                            @endif
                        </a>
                    @else
                        <span style="color: var(--storefront-announcement-text);" class="font-medium text-amber-600 dark:text-amber-300">{{ data_get($announcementCenter, 'text') }}</span>
                        @if(filled(data_get($announcementCenter, 'couponCode')))
                            <span> • Code </span>
                            <span style="background-color: var(--storefront-announcement-badge-background); color: var(--storefront-announcement-text);" class="font-mono bg-zinc-200 dark:bg-zinc-700/80 px-1.5 py-0.5 rounded text-zinc-900 dark:text-zinc-100 font-semibold">{{ data_get($announcementCenter, 'couponCode') }}</span>
                        @endif
                    @endif
                @endif
            </div>
            @if($rightVisible)
                <div style="color: var(--storefront-announcement-text);" class="hidden md:flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                    @if(filled(data_get($announcementRight, 'helperText')))
                        <span class="text-[11px] font-mono">{{ data_get($announcementRight, 'helperText') }}</span>
                    @endif
                    @if($trackingVisible)
                        <a href="{{ data_get($announcementRight, 'trackingUrl') }}" class="text-[11px] font-mono">{{ data_get($announcementRight, 'trackingLabel') }}</a>
                    @endif
                    @if($whatsappVisible)
                        <a href="{{ data_get($announcementRight, 'whatsappUrl') }}" class="text-[11px] font-mono">{{ data_get($announcementRight, 'whatsappLabel') }}</a>
                    @endif
                </div>
            @endif
        </div>
    @endif
    <div data-header-main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-3">
                <a href="{{ route('storefront.home') }}" class="group cursor-pointer flex items-center gap-2.5 p-1 rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-all" title="{{ $shopBrandName }}" aria-label="{{ $shopBrandName }} home">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl overflow-hidden bg-black flex items-center justify-center shadow-md ring-1 ring-zinc-200 dark:ring-zinc-800 group-hover:ring-amber-500/50 group-hover:shadow-amber-500/20 group-hover:shadow-lg transition-all duration-300"><img data-header-logo data-overlay-logo="{{ data_get($storefrontShell,'overlayLogos.'.$presentation['tone']) }}" src="{{ app(\App\Support\ShopBrand::class)->logo() }}" alt="JW Studio Logo" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" referrerpolicy="no-referrer"></div>
                    <div class="hidden xl:flex flex-col text-left"><span class="font-serif font-black tracking-widest text-sm text-zinc-950 dark:text-white uppercase">{{ $shopBrandName }}</span><span class="text-[9px] tracking-widest text-zinc-400 dark:text-zinc-500 font-mono -mt-0.5">EST. 2026</span></div>
                </a>
            </div>
            <nav class="hidden lg:flex items-center gap-1">
                @foreach($menuItems as $item)
                    <div data-header-menu="{{ $item['id'] }}" class="relative" @mouseenter="activeMenuId = @js($item['direct']) ? null : @js($item['id'])" @focusin="activeMenuId = @js($item['direct']) ? null : @js($item['id'])">
                        <a href="{{ $item['url'] }}" :class="activeMenuId === @js($item['id']) ? 'bg-zinc-900 dark:bg-zinc-800 text-white dark:text-zinc-100 shadow-md ring-1 ring-zinc-700/50' : 'text-zinc-700 dark:text-zinc-300 hover:text-zinc-950 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-900'" class="flex items-center px-3.5 py-2 rounded-xl text-xs font-semibold tracking-wide transition-all cursor-pointer">
                            <span>{{ $item['label'] }}</span>
                            @if(! empty($item['badge']))
                                <span
                                    @class([
                                        'ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border',
                                        'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/30' => $item['badgeType'] === 'popular',
                                        'bg-purple-500/20 text-purple-600 dark:text-purple-300 border-purple-500/40' => in_array($item['badgeType'], ['sale', 'discount'], true),
                                        'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30' => in_array($item['badgeType'], ['accent', 'hot'], true),
                                        'bg-zinc-200/80 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-300/60 dark:border-zinc-700' => ! in_array($item['badgeType'], ['popular', 'sale', 'discount', 'accent', 'hot'], true),
                                    ])
                                    style="{{ $badgeStyle($item['badgeColor']) }}"
                                >{{ $item['badge'] }}</span>
                            @endif
                            @if(! $item['direct'])<i data-lucide="chevron-down" class="w-3.5 h-3.5 ml-1.5 text-zinc-400 transition-transform duration-200"></i>@endif
                        </a>
                    </div>
                @endforeach
            </nav>
            <div class="flex items-center gap-1.5 sm:gap-2.5">
                <div class="relative">
                    <form x-show="searchOpen" x-cloak action="{{ route('storefront.index') }}" class="flex items-center bg-zinc-100 dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-full px-3 py-1.5 text-xs shadow-inner">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 mr-2 shrink-0"></i><input name="search" placeholder="Search audio, desk, watch..." class="bg-transparent border-none outline-none text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 w-36 sm:w-52 text-xs"><button type="button" @click="searchOpen = false" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 ml-1.5 p-0.5 rounded-full hover:bg-zinc-200 dark:hover:bg-zinc-800"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                    </form>
                    <button x-show="!searchOpen" @click="searchOpen = true" class="p-2 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors cursor-pointer" title="Search Catalog" aria-label="Search Catalog"><i data-lucide="search" class="w-4 h-4"></i></button>
                </div>
                <button data-atelier-theme-toggle id="theme-toggle-button" class="p-2 text-zinc-600 dark:text-zinc-400 hover:text-zinc-950 dark:hover:text-white rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-all cursor-pointer flex items-center justify-center border border-zinc-200/80 dark:border-zinc-800" title="Switch to Light Mode (切换亮底)" aria-label="Toggle Dark or Light Mode"><i data-atelier-theme-icon data-lucide="sun" class="w-4 h-4 text-amber-400 hover:rotate-45 transition-transform duration-300"></i></button>
                <div class="hidden sm:flex items-center text-xs text-zinc-600 dark:text-zinc-400 border-l border-zinc-200 dark:border-zinc-800 pl-2"><i data-lucide="globe" class="w-3.5 h-3.5 mr-1 text-zinc-500 dark:text-zinc-500"></i><select data-atelier-currency aria-label="Select Currency" class="bg-transparent text-zinc-700 dark:text-zinc-300 text-xs font-mono outline-none cursor-pointer hover:text-zinc-950 dark:hover:text-white">@foreach($currencies as $currency)<option value="{{ $currency['code'] }}" @selected($currency['code'] === $selectedCurrency['code']) class="bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100">{{ $currency['code'] }} ({{ trim($currency['symbolLeft'].$currency['symbolRight']) }})</option>@endforeach</select></div>
                <div class="relative">
                    <button @click="userDropdownOpen = !userDropdownOpen" class="relative p-2 rounded-full border transition-all cursor-pointer flex items-center gap-1 bg-amber-500/10 border-amber-500/40 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20" title="{{ $memberUser ? 'Account: '.$memberUser->name : 'User Login / Register (用户登录 / 注册)' }}" aria-label="User Account Menu">@if($memberUser)<div class="w-4 h-4 rounded-full bg-amber-500 text-zinc-950 text-[10px] font-bold flex items-center justify-center">{{ $initial }}</div>@else<i data-lucide="user" class="w-4 h-4"></i>@endif<i data-lucide="chevron-down" class="w-3 h-3 text-zinc-400 transition-transform duration-200"></i></button>
                    <div x-show="userDropdownOpen" x-cloak @click.outside="userDropdownOpen = false" class="absolute right-0 mt-2 w-64 sm:w-72 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-2xl p-2 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                        @if($memberUser)
                            <div class="space-y-1.5"><div class="p-3 bg-zinc-50 dark:bg-zinc-950/70 rounded-xl border border-zinc-100 dark:border-zinc-800"><div class="flex items-center gap-2.5"><div class="w-9 h-9 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-950 font-bold text-sm flex items-center justify-center font-serif shadow">{{ $initial }}</div><div class="flex-1 min-w-0"><div class="text-xs font-bold text-zinc-900 dark:text-white truncate">{{ $memberUser->name }}</div><div class="text-[11px] text-zinc-500 truncate font-mono">{{ $memberUser->email }}</div></div></div></div>
                                <a href="{{ route('customer.account').'#purchase_history' }}" @click="userDropdownOpen = false" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white transition-colors cursor-pointer"><i data-lucide="package" class="w-4 h-4 text-zinc-400"></i><span>My Orders & Dispatch (我的订单与物流)</span></a>
                                <a href="{{ route('customer.account').'#profile' }}" @click="userDropdownOpen = false" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white transition-colors cursor-pointer"><i data-lucide="user" class="w-4 h-4 text-zinc-400"></i><span>Account & Addresses (个人资料与地址)</span></a>
                                <div class="pt-1 border-t border-zinc-100 dark:border-zinc-800"><form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"><i data-lucide="log-out" class="w-4 h-4"></i><span>Log Out (退出登录)</span></button></form></div>
                            </div>
                        @else
                            <div class="space-y-1.5">
                                <a href="{{ route('customer.login') }}" @click="userDropdownOpen = false" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-950 hover:bg-amber-400 hover:text-zinc-950 transition-colors cursor-pointer"><i data-lucide="log-in" class="w-4 h-4"></i><span>Sign In (用户登录)</span></a>
                                <a href="{{ route('customer.register') }}" @click="userDropdownOpen = false" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white transition-colors cursor-pointer"><i data-lucide="user-plus" class="w-4 h-4 text-zinc-400"></i><span>Register (新用户注册)</span></a>
                                <a href="{{ route('customer.password.request') }}" @click="userDropdownOpen = false" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white transition-colors cursor-pointer"><i data-lucide="key-round" class="w-4 h-4 text-zinc-400"></i><span>Forgot Password? (找回密码)</span></a>
                            </div>
                        @endif
                    </div>
                </div>
                <button data-cart-drawer-open class="flex items-center gap-1.5 px-3 py-2 bg-zinc-900 dark:bg-zinc-800 hover:bg-zinc-800 dark:hover:bg-zinc-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all cursor-pointer" aria-label="Open Shopping Cart"><i data-lucide="shopping-bag" class="w-4 h-4 text-amber-400"></i><span>Cart (<span data-cart-count-badge>{{ $cartCount }}</span>)</span></button>
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors" aria-label="Toggle Mobile Navigation Drawer"><i data-lucide="menu" class="w-5 h-5"></i></button>
            </div>
        </div>
    </div>

    @foreach($megaMenuDetails as $menuId => $menu)
        <div x-show="activeMenuId === @js($menuId)" x-cloak @mouseenter="activeMenuId = @js($menuId)" @focusin="activeMenuId = @js($menuId)" class="absolute top-full left-0 right-0 w-full bg-white/95 dark:bg-zinc-950/95 backdrop-blur-xl border-b border-zinc-200/80 dark:border-zinc-800/80 shadow-2xl z-50 animate-in fade-in slide-in-from-top-2 duration-200" data-header-menu-panel="{{ $menuId }}" id="mega-menu-{{ $menuId }}">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7"><div class="grid grid-cols-12 gap-8"><div class="grid gap-6 col-span-12 lg:col-span-8 grid-cols-1 md:grid-cols-3">
                @foreach($menu['columns'] as $column)
                    <div class="space-y-3"><button data-mobile-menu-url="{{ $column->url }}" @click="window.location.assign(@js($column->url))" class="group w-full flex items-center justify-between pb-2 border-b border-zinc-200/80 dark:border-zinc-800/80 cursor-pointer"><div class="text-left"><h4 class="text-sm font-bold text-zinc-950 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors flex items-center gap-2"><span>{{ $column->label }}</span></h4><span class="text-[11px] text-zinc-400 dark:text-zinc-500 block font-normal">{{ $column->label_cn }}</span></div>@if($column->badge)<span @class(['inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 animate-pulse' => $column->badge_type === 'hot', 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border-cyan-500/30' => $column->badge_type === 'rated', 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/30' => in_array($column->badge_type, ['sale', 'discount'], true), 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' => $column->badge_type === 'new', 'bg-zinc-200/80 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-300/60 dark:border-zinc-700' => ! in_array($column->badge_type, ['hot', 'rated', 'sale', 'discount', 'new'], true)]) style="{{ $badgeStyle($column->badge_color) }}">{{ $column->badge }}</span>@endif</button><ul class="space-y-1.5 pt-1">
                        @foreach($column->children as $subItem)<li><button data-mobile-menu-url="{{ $subItem->url }}" @click="window.location.assign(@js($subItem->url))" class="group w-full flex items-center justify-between py-1.5 px-2 rounded-lg text-left text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-950 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-900/80 transition-all cursor-pointer"><div class="flex flex-col min-w-0 pr-2"><span class="font-medium group-hover:translate-x-0.5 transition-transform truncate">{{ $subItem->label }}</span><span class="text-[10px] text-zinc-400 dark:text-zinc-500 group-hover:text-zinc-500 dark:group-hover:text-zinc-400 truncate">{{ $subItem->label_cn }}</span></div><div class="shrink-0 flex items-center gap-1.5">@if($subItem->badge)<span @class(['inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 animate-pulse' => $subItem->badge_type === 'hot', 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' => $subItem->badge_type === 'new', 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border-cyan-500/30' => $subItem->badge_type === 'rated', 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/30' => in_array($subItem->badge_type, ['sale', 'discount'], true), 'bg-zinc-200/80 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-300/60 dark:border-zinc-700' => ! in_array($subItem->badge_type, ['hot', 'new', 'rated', 'sale', 'discount'], true)]) style="{{ $badgeStyle($subItem->badge_color) }}">{{ $subItem->badge }}</span>@endif<i data-lucide="arrow-right" class="w-3 h-3 opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-amber-500"></i></div></button></li>@endforeach
                    </ul></div>
                @endforeach
            </div>@if($menu['promo'])<div class="col-span-12 lg:col-span-4 pl-0 lg:pl-6 lg:border-l border-zinc-200/80 dark:border-zinc-800/80 flex flex-col justify-between"><button data-mobile-menu-url="{{ $menu['promo']['ctaUrl'] }}" @click="window.location.assign(@js($menu['promo']['ctaUrl']))" class="group relative rounded-2xl overflow-hidden bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 cursor-pointer hover:border-amber-500/40 hover:shadow-xl transition-all text-left"><div class="relative aspect-[16/10] w-full rounded-xl overflow-hidden bg-zinc-950 mb-3.5"><img src="{{ $menu['promo']['imageUrl'] }}" alt="{{ $menu['promo']['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" referrerpolicy="no-referrer"><div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>@if($menu['promo']['badge'])<div class="absolute top-2.5 left-2.5"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-zinc-950/80 text-amber-400 border border-amber-500/30 backdrop-blur-md">{{ $menu['promo']['badge'] }}</span></div>@endif@if($menu['promo']['price'])<div class="absolute bottom-2.5 left-2.5 right-2.5 flex items-center justify-between text-white"><span class="text-xs font-mono font-bold">{{ $menu['promo']['price'] }}</span></div>@endif</div><div><h4 class="text-sm font-bold text-zinc-950 dark:text-white group-hover:text-amber-500 transition-colors">{{ $menu['promo']['title'] }}</h4>@if($menu['promo']['description'])<p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-2 leading-relaxed">{{ $menu['promo']['description'] }}</p>@endif@if($menu['promo']['ctaText'])<div class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-amber-600 dark:text-amber-400 group-hover:text-amber-500"><span>{{ $menu['promo']['ctaText'] }}</span><i data-lucide="arrow-right" class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform"></i></div>@endif</div></button><div class="mt-3 pt-2.5 border-t border-zinc-200/60 dark:border-zinc-800/60 flex items-center justify-between text-[11px] text-zinc-400"><span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>In Stock & Ready for Express Dispatch</span><span class="font-mono">30-Day Trial</span></div></div>@endif</div></div>
        </div>
    @endforeach

    <div x-show="mobileMenuOpen" x-cloak class="lg:hidden bg-white dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800 px-4 py-4 space-y-4 max-h-[85vh] overflow-y-auto animate-in slide-in-from-top-2 duration-200">
        <form action="{{ route('storefront.index') }}" class="relative"><input name="search" placeholder="Search all headphones, keyboards, bags..." class="w-full bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl px-3.5 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 outline-none"><button class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-zinc-500 hover:text-zinc-900 dark:hover:text-white"><i data-lucide="search" class="w-4 h-4"></i></button></form>
        @if($memberUser)
            <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900/80 border border-zinc-200 dark:border-zinc-800 flex items-center justify-between"><div class="flex items-center justify-between w-full"><div class="flex items-center gap-2.5"><div class="w-8 h-8 rounded-lg bg-amber-500 text-zinc-950 font-bold text-xs flex items-center justify-center font-serif">{{ $initial }}</div><div><div class="text-xs font-bold text-zinc-900 dark:text-white">{{ $memberUser->name }}</div><div class="text-[10px] text-amber-600 dark:text-amber-400 font-mono">{{ $memberUser->email }}</div></div></div><div class="flex items-center gap-1.5"><a href="{{ route('customer.account') }}" @click="mobileMenuOpen = false" class="px-2.5 py-1 rounded-lg bg-zinc-900 dark:bg-zinc-800 text-white text-[11px] font-medium">Account</a><form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-950"><i data-lucide="log-out" class="w-3.5 h-3.5"></i></button></form></div></div></div>
        @else
            <a href="{{ route('customer.login') }}" @click="mobileMenuOpen = false" class="px-2.5 py-1 rounded-lg bg-zinc-900 dark:bg-zinc-800 text-white text-[11px] font-medium">Sign In</a>
        @endif
        <div class="space-y-1.5">
            <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider px-1">Explore Collections & Categories</div>
            @foreach($menuItems as $layer1)
                @php($hasDropdown = ! $layer1['direct'] && isset($megaMenuDetails[$layer1['id']]))
                <div class="rounded-xl border border-zinc-200/70 dark:border-zinc-800/80 overflow-hidden bg-zinc-50/50 dark:bg-zinc-900/40">
                    <button
                        type="button" data-mobile-menu-toggle="{{ $layer1['id'] }}" data-mobile-menu-url="{{ $hasDropdown ? '' : $layer1['url'] }}"
                        @click="@if($hasDropdown) mobileExpandedLayer1 = mobileExpandedLayer1 === @js($layer1['id']) ? null : @js($layer1['id']); mobileExpandedLayer2 = null @else window.location.assign(@js($layer1['url'])); mobileMenuOpen = false @endif"
                        class="w-full flex items-center justify-between p-3 text-left text-xs font-bold text-zinc-900 dark:text-white"
                    >
                        <div class="flex items-center">
                            <span>{{ $layer1['label'] }}</span>
                            @if(! empty($layer1['badge']))
                                <span
                                    @class([
                                        'ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border',
                                        'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/30' => $layer1['badgeType'] === 'popular',
                                        'bg-purple-500/20 text-purple-600 dark:text-purple-300 border-purple-500/40' => in_array($layer1['badgeType'], ['sale', 'discount'], true),
                                        'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30' => in_array($layer1['badgeType'], ['accent', 'hot'], true),
                                        'bg-zinc-200/80 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-300/60 dark:border-zinc-700' => ! in_array($layer1['badgeType'], ['popular', 'sale', 'discount', 'accent', 'hot'], true),
                                    ])
                                    style="{{ $badgeStyle($layer1['badgeColor']) }}"
                                >{{ $layer1['badge'] }}</span>
                            @endif
                        </div>
                        @if($hasDropdown)
                            <i data-lucide="chevron-down" :class="mobileExpandedLayer1 === @js($layer1['id']) ? 'rotate-180 text-amber-500' : ''" class="w-4 h-4 text-zinc-400 transition-transform duration-200"></i>
                        @else
                            {!! $sourceIcon('chevron-right', 'w-4 h-4 text-zinc-400') !!}
                        @endif
                    </button>

                    @if($hasDropdown)
                        <div data-mobile-menu-panel="{{ $layer1['id'] }}" x-show="mobileExpandedLayer1 === @js($layer1['id'])" x-cloak class="px-3 pb-3 space-y-2 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 pt-2.5">
                            @foreach($megaMenuDetails[$layer1['id']]['columns'] as $columnIndex => $column)
                                @php($columnId = $layer1['id'].'-'.$columnIndex)
                                <div class="rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 overflow-hidden">
                                    <button type="button" data-mobile-menu-toggle="{{ $columnId }}" @click="mobileExpandedLayer2 = mobileExpandedLayer2 === @js($columnId) ? null : @js($columnId)" class="w-full flex items-center justify-between p-2.5 text-left bg-zinc-100/60 dark:bg-zinc-900/60 text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                        <div class="flex items-center gap-1.5"><span>{{ $column->label }}</span>@if($column->badge)<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400" style="{{ $badgeStyle($column->badge_color) }}">{{ $column->badge }}</span>@endif</div>
                                        <i data-lucide="chevron-down" :class="mobileExpandedLayer2 === @js($columnId) ? 'rotate-180 text-amber-500' : ''" class="w-3.5 h-3.5 text-zinc-400 transition-transform"></i>
                                    </button>
                                    <div data-mobile-menu-panel="{{ $columnId }}" x-show="mobileExpandedLayer2 === @js($columnId)" x-cloak class="p-2 space-y-1 bg-zinc-50 dark:bg-zinc-950">
                                        @foreach($column->children as $layer3)
                                            <button type="button" data-mobile-menu-url="{{ $layer3->url }}" @click="window.location.assign(@js($layer3->url)); mobileMenuOpen = false" class="w-full flex items-center justify-between py-1.5 px-2 rounded text-left text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                                                <span class="truncate">{{ $layer3->label }}</span>
                                                @if($layer3->badge)<span class="shrink-0 px-1 py-0.2 rounded text-[9px] font-bold uppercase bg-rose-500/20 text-rose-500" style="{{ $badgeStyle($layer3->badge_color) }}">{{ $layer3->badge }}</span>@endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            @php($promo = $megaMenuDetails[$layer1['id']]['promo'])
                            @if($promo)
                                <div data-mobile-menu-url="{{ $promo['ctaUrl'] }}" @click="window.location.assign(@js($promo['ctaUrl'])); mobileMenuOpen = false" class="mt-2 p-2.5 rounded-xl bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-2.5"><img src="{{ $promo['imageUrl'] }}" alt="{{ $promo['title'] }}" class="w-10 h-10 rounded-lg object-cover" referrerpolicy="no-referrer"><div><div class="text-[11px] font-bold text-zinc-900 dark:text-white truncate">{{ $promo['title'] }}</div>@if($promo['price'])<div class="text-[10px] text-amber-600 dark:text-amber-400 font-mono">{{ $promo['price'] }}</div>@endif</div></div>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-zinc-400"></i>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="pt-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between text-xs"><button data-atelier-theme-toggle class="flex items-center gap-1.5 py-1.5 px-3 rounded-lg bg-zinc-100 dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300"><i data-atelier-theme-icon data-lucide="sun" class="w-3.5 h-3.5 text-amber-400"></i><span data-atelier-theme-label>Light Mode</span></button><div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400"><i data-lucide="globe" class="w-3.5 h-3.5"></i><select data-atelier-currency aria-label="Select Currency" class="bg-zinc-100 dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 py-1.5 px-2 rounded-lg text-xs font-mono outline-none">@foreach($currencies as $currency)<option value="{{ $currency['code'] }}" @selected($currency['code'] === $selectedCurrency['code']) class="bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100">{{ $currency['code'] }} ({{ trim($currency['symbolLeft'].$currency['symbolRight']) }})</option>@endforeach</select></div></div>
    </div>
</header>
@endif

<template data-atelier-currency-options data-atelier-currency-default="{{ $selectedCurrency['code'] }}" data-atelier-currency-endpoint="{{ route('storefront.currency.update') }}" data-atelier-currency-symbol-left="{{ $selectedCurrency['symbolLeft'] }}" data-atelier-currency-symbol-right="{{ $selectedCurrency['symbolRight'] }}" data-atelier-currency-decimals="{{ $selectedCurrency['decimalPlaces'] }}" data-atelier-currency-rate="{{ $selectedCurrency['exchangeRate'] }}">@foreach($currencies as $currency)<option value="{{ $currency['code'] }}" @selected($currency['code'] === $selectedCurrency['code']) class="bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">{{ $currency['code'] }} ({{ trim($currency['symbolLeft'].$currency['symbolRight']) }})</option>@endforeach</template>

@if(! $design)
@once
<link rel="stylesheet" href="{{ asset('css/cms-header-overlay.css') }}">
<script src="{{ asset('js/cms-header-overlay.js') }}" defer></script>
@endonce

@endif
