<header data-shell-header data-shell-style="{{ $design['style'] }}" data-shell-business="{{ $design['business'] }}" data-header-mode="{{ $presentation['mode'] }}" data-header-tone="{{ $presentation['tone'] }}" data-mega-nav  class="jw-shell-header">
    @if($design['showAnnouncement'] && $announcementVisible)
    <div class="jw-shell-announcement" style="{{ $announcementStyle }}">
        @if($centerVisible)<span>{{ data_get($announcementCenter,'text') }} @if(filled(data_get($announcementCenter,'couponCode'))) · {{ data_get($announcementCenter,'couponCode') }} @endif</span>
        @elseif($leftVisible)<span>{{ data_get($announcementLeft,'text') }}</span>
        @elseif($rightVisible)<span>{{ data_get($announcementRight,'helperText') }}</span>@endif
    </div>
    @endif
    <div data-header-main class="jw-shell-main">
        <button type="button" class="jw-shell-menu jw-shell-icon" aria-label="Open navigation" aria-expanded="false" data-shell-menu-toggle><i data-lucide="menu"></i></button>
        <a class="jw-shell-brand" href="{{ route('storefront.home') }}"><x-storefront.shell-brand :design="$design" :presentation="$presentation" header /></a>
        <nav class="jw-shell-desktop" aria-label="Main navigation">
            @foreach($menuItems as $item)
                @if($item['direct'])<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    @php($grouped = $item['columns']->contains(fn ($column) => $column->children->where('is_active', true)->isNotEmpty()))
                    <details class="jw-shell-dropdown" data-menu-depth="{{ $grouped ? 2 : 1 }}">
                        <summary>{{ $item['label'] }}</summary>
                        <div class="jw-shell-submenu {{ $grouped ? 'jw-shell-submenu-grouped' : '' }}">
                            @foreach($item['columns'] as $column)
                                @if($grouped)
                                    <section class="jw-shell-menu-group"><a class="jw-shell-group-title" href="{{ $column->url }}">{{ $column->label }}</a>
                                    @foreach($column->children->where('is_active',true) as $child)<a href="{{ $child->url }}">{{ $child->label }}</a>@endforeach
                                    </section>
                                @else<a href="{{ $column->url }}">{{ $column->label }}</a>@endif
                            @endforeach
                            <a class="jw-shell-menu-all" href="{{ $item['url'] }}">{{ $item['label'] }} <span aria-hidden="true">→</span></a>
                        </div>
                    </details>
                @endif
            @endforeach
        </nav>
        <div class="jw-shell-tools">
            @if(app(\App\Services\WebsiteDesignService::class)->surface()==='food')<form class="fd-shell-search" method="GET" action="{{ route('storefront.index') }}"><i data-lucide="search"></i><input name="search" aria-label="Search pantry favourites" placeholder="Search for pantry favourites…"></form>@endif
            @if($design['business']==='commerce')
                <button type="button" class="jw-shell-icon" aria-label="Search products" data-shell-search-toggle aria-expanded="false"><i data-lucide="search"></i></button>
                <a href="{{ route('cart.show') }}" data-cart-drawer-open data-cart-drawer-trigger class="jw-shell-icon" aria-label="Shopping bag"><i data-lucide="shopping-bag"></i><span data-cart-count-badge data-cart-count class="jw-shell-count">{{ $cartCount }}</span></a>
            @endif
            @if($design['showThemeSwitch'])<button type="button" data-atelier-theme-toggle class="jw-shell-icon" aria-label="Switch light or dark appearance"><i data-atelier-theme-icon data-lucide="{{ $design['appearance']==='dark'?'sun':'moon' }}"></i></button>@endif
            @if($design['showAccount'])<a class="jw-shell-icon jw-shell-optional" href="{{ $memberUser?route('customer.account'):route('customer.login') }}" aria-label="Account"><i data-lucide="user"></i></a>@endif
            @if($design['showCurrency'])<select data-atelier-currency aria-label="Select currency" class="jw-shell-currency jw-shell-optional">@foreach($currencies as $currency)<option value="{{ $currency['code'] }}" @selected($currency['code']===$selectedCurrency['code'])>{{ $currency['code'] }}</option>@endforeach</select>@endif
            @if(filled($design['ctaLabel']) && filled($design['ctaUrl']))<a href="{{ $design['ctaUrl'] }}" class="jw-shell-cta" aria-label="{{ $design['ctaLabel'] }}"><span>{{ $design['ctaLabel'] }}</span><i data-lucide="arrow-right"></i></a>@endif
        </div>
    </div>
    @if($design['business']==='commerce')<form data-shell-search-panel hidden class="jw-shell-search" action="{{ route('storefront.index') }}"><label for="shell-product-search" class="sr-only">Search products</label><input id="shell-product-search" name="search" placeholder="Search products…"><button type="submit" class="jw-shell-icon" aria-label="Submit search"><i data-lucide="search"></i></button></form>@endif
    <nav data-shell-menu-panel hidden class="jw-shell-mobile" aria-label="Mobile navigation">
        @foreach($menuItems as $item)
            @if($item['direct'])<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @else<details><summary>{{ $item['label'] }}</summary><div class="jw-shell-mobile-group"><a href="{{ $item['url'] }}">{{ $item['label'] }} →</a>@foreach($item['columns'] as $column)@if($column->children->where('is_active',true)->isEmpty())<a href="{{ $column->url }}">{{ $column->label }}</a>@else<details><summary>{{ $column->label }}</summary><a href="{{ $column->url }}">{{ $column->label }} →</a>@foreach($column->children->where('is_active',true) as $child)<a href="{{ $child->url }}">{{ $child->label }}</a>@endforeach</details>@endif @endforeach</div></details>@endif
        @endforeach
        @if($design['showAccount'])<a href="{{ $memberUser?route('customer.account'):route('customer.login') }}">Account</a>@endif
        @if($design['showCurrency'])<label>Currency <select data-atelier-currency aria-label="Mobile currency">@foreach($currencies as $currency)<option value="{{ $currency['code'] }}" @selected($currency['code']===$selectedCurrency['code'])>{{ $currency['code'] }}</option>@endforeach</select></label>@endif
    </nav>
</header>
<link rel="stylesheet" href="{{ asset('css/cms-shell-design.css') }}?v={{ filemtime(public_path('css/cms-shell-design.css')) }}">
<link rel="stylesheet" href="{{ asset('css/cms-header-overlay.css') }}?v={{ filemtime(public_path('css/cms-header-overlay.css')) }}">
<script src="{{ asset('js/cms-shell-design.js') }}?v={{ filemtime(public_path('js/cms-shell-design.js')) }}" defer></script>
