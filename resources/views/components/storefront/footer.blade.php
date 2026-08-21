@php
    $footerCategories = \App\Models\Category::query()->where('is_active', true)->orderBy('name')->limit(5)->get();
    $footerProduct = \App\Models\Product::query()->where('status', 'active')->orderBy('id')->first();
    $footerMenu = \App\Models\Menu::query()->where('key', 'footer')->with([
        'rootItems' => fn ($query) => $query->where('is_active', true),
        'rootItems.children' => fn ($query) => $query->where('is_active', true),
    ])->first();
    $footerSections = $footerMenu?->rootItems?->where('is_active', true)->values() ?? collect();
    $footerGroups = $footerSections->isNotEmpty() ? $footerSections->chunk((int) ceil($footerSections->count() / 2)) : collect();
@endphp

<footer class="border-t border-zinc-200 bg-zinc-100 font-sans text-zinc-600 transition-colors duration-200 dark:border-zinc-900 dark:bg-zinc-950 dark:text-zinc-400">
    <x-storefront.trust-strip />
    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 py-12 sm:px-6 md:grid-cols-5 lg:px-8">
        <section class="space-y-4 md:col-span-2">
            <a href="{{ route('storefront.home') }}" aria-label="ATELIER home" class="inline-flex rounded-lg transition hover:opacity-80"><img src="{{ asset('images/source/jwstudio_logo_1786766390670.jpg') }}" alt="JW Studio Logo" class="h-8 w-8 rounded-lg object-cover shadow ring-1 ring-zinc-200 dark:ring-zinc-800"></a>
            <p class="max-w-sm text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">Precision-crafted studio audio systems, ergonomic desk architectures, mechanical timepieces, and everyday carry essentials for intentional living.</p>
        </section>
        @forelse($footerGroups as $groupIndex => $group)
        <nav aria-label="Footer Navigation {{ $groupIndex + 1 }}">
            @foreach($group as $section)
            <div @class(['mt-6' => ! $loop->first])>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-900 dark:text-zinc-200">{{ $section->label }}</h2>
            <ul class="space-y-2 text-xs">@foreach($section->children->where('is_active', true) as $link)<li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ $link->url }}" @if($link->is_external) target="_blank" rel="noopener" @endif>{{ $link->label }}</a></li>@endforeach</ul>
            </div>
            @endforeach
        </nav>
        @empty
        <nav aria-label="Core Page Navigation">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-900 dark:text-zinc-200">Core Page Navigation</h2>
            <ul class="space-y-2 text-xs">
                <li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ route('storefront.home') }}">1. Landing &amp; Collections</a></li>
                <li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ route('storefront.index') }}">2. PLP (Catalog &amp; Filter)</a></li>
                @if($footerProduct)<li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ route('storefront.products.show', $footerProduct) }}">3. PDP (Product &amp; Variants)</a></li>@endif
                <li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ route('cart.show') }}">4. Shopping Cart</a></li>
                <li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ route('checkout.create') }}">5. Checkout &amp; Payment</a></li>
                <li><a class="transition-colors hover:text-amber-600 dark:hover:text-amber-300" href="{{ route('checkout.create') }}">6. Order Success &amp; Tracking</a></li>
            </ul>
        </nav>
        <nav aria-label="Design Verticals">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-900 dark:text-zinc-200">Design Verticals</h2>
            <ul class="space-y-2 text-xs">@foreach($footerCategories as $category)<li><a class="transition-colors hover:text-zinc-900 dark:hover:text-zinc-200" href="{{ route('storefront.index', ['category' => $category->slug]) }}">{{ $category->name }}</a></li>@endforeach</ul>
        </nav>
        @endforelse
        <section class="space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-900 dark:text-zinc-200">Join The Atelier</h2>
            <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">Subscribe for early access to seasonal capsule drops &amp; instant 10% coupon code.</p>
            <div class="flex gap-2" role="group" aria-label="Newsletter interest field"><input type="email" autocomplete="email" placeholder="studio@domain.com" aria-label="Newsletter email" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 outline-none placeholder:text-zinc-400 focus:border-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"><button type="button" aria-label="Join newsletter (signup service not configured)" title="Newsletter signup is not configured" class="rounded-lg bg-zinc-900 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-amber-500 hover:text-zinc-950 dark:bg-zinc-100 dark:text-zinc-950">Join</button></div>
        </section>
    </div>
    <div class="mx-auto flex max-w-7xl flex-col gap-4 border-t border-zinc-200 px-4 py-6 text-xs sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8 dark:border-zinc-900"><span>© 2026 ATELIER Design Studio Inc. All rights reserved.</span><span class="font-mono">Privacy Policy&nbsp;&nbsp;•&nbsp;&nbsp;Terms of Service&nbsp;&nbsp;•&nbsp;&nbsp;Global Compliance</span></div>
</footer>
