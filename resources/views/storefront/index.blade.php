@php
    $collectionTitle = $collection?->title;
    $listingTitle = $collectionTitle ?? ($category ? ($categories->firstWhere('slug', $category)?->name ?? 'Category Collection') : 'All Atelier Collections');
    $listingEyebrow = 'Catalog & Product Listing'
        . ($collection ? ' • ' . ($collection->badge ?: $collection->title) : '')
        . ' • ' . $products->count() . ' Items Found';
    $listingDescription = $collection?->subtitle ?: 'Precision engineered audio systems, ergonomic desk architectures, titanium watches, and weatherproof carry systems.';
@endphp
<x-layouts.app :title="$listingTitle . ' | ATELIER'">
    <div class="min-h-screen bg-zinc-50 font-sans text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <x-storefront.nav active="plp" />

        <main>
            <section class="border-b border-zinc-200 bg-zinc-50 px-4 py-10 sm:px-6 lg:px-8 dark:border-zinc-900 dark:bg-gradient-to-b dark:from-zinc-900/50 dark:to-zinc-950">
                <div class="mx-auto max-w-7xl">
                    <p class="font-mono text-xs uppercase tracking-widest text-amber-600 dark:text-amber-400">{{ $listingEyebrow }}</p>
                    <h1 class="mt-1 font-serif text-3xl font-bold text-zinc-900 dark:text-white">{{ $listingTitle }}</h1>
                    <p class="mt-1 max-w-xl text-xs text-zinc-600 dark:text-zinc-400">{{ $listingDescription }}</p>
                </div>
            </section>

            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 pt-8 sm:px-6 lg:grid-cols-12 lg:px-8">
                <aside class="hidden lg:block lg:col-span-3">
                    <x-storefront.plp-filters :categories="$categories" :products="$products" :colors="$colors" :search="$search" :category="$category" :sort="$sort" :max-price="$maxPrice" :in-stock="$inStock" :color="$color" :layout="$layout" :route-name="$listingRouteName" :route-parameters="$listingRouteParameters" />
                </aside>

                <section class="space-y-6 lg:col-span-9">
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-3.5 shadow-sm dark:border-zinc-800/90 dark:bg-zinc-900/60">
                        <details class="relative lg:hidden">
                            <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200"><i data-lucide="sliders-horizontal" class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400"></i>Filters</summary>
                            <div class="absolute left-0 top-10 z-30 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-zinc-200 bg-white p-4 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"><x-storefront.plp-filters :categories="$categories" :products="$products" :colors="$colors" :search="$search" :category="$category" :sort="$sort" :max-price="$maxPrice" :in-stock="$inStock" :color="$color" :layout="$layout" :route-name="$listingRouteName" :route-parameters="$listingRouteParameters" /></div>
                        </details>
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">Showing <b class="text-zinc-900 dark:text-zinc-200">{{ $products->count() }}</b> items</span>
                        <form method="GET" data-catalog-toolbar-sort class="flex items-center gap-3">
                            @foreach(['search' => $search, 'category' => $category, 'max_price' => $maxPrice, 'color' => $color, 'in_stock' => $inStock ?: null, 'layout' => $layout] as $key => $value)
                                @if(filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                            @endforeach
                            <label class="flex items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-400">Sort by:
                                <select name="sort" onchange="this.form.submit()" class="rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs text-zinc-800 outline-none focus:border-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:focus:border-amber-400">
                                    <option value="">Featured Curations</option><option value="price_asc" @selected($sort === 'price_asc')>Price: Low to High</option><option value="price_desc" @selected($sort === 'price_desc')>Price: High to Low</option><option value="rating">Customer Rating</option><option value="newest">Newest Releases</option>
                                </select>
                            </label>
                            <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-100 p-0.5 dark:border-zinc-800 dark:bg-zinc-900">
                                <a title="Grid View" aria-label="Grid View" href="{{ route($listingRouteName, array_merge($listingRouteParameters, array_filter(request()->except('layout') + ['layout' => 'grid']))) }}" class="rounded-md p-1.5 {{ $layout === 'grid' ? 'bg-white text-amber-600 shadow-sm dark:bg-zinc-800 dark:text-amber-400' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"><i data-lucide="grid" class="h-4 w-4"></i></a>
                                <a title="List View" aria-label="List View" href="{{ route($listingRouteName, array_merge($listingRouteParameters, array_filter(request()->except('layout') + ['layout' => 'list']))) }}" class="rounded-md p-1.5 {{ $layout === 'list' ? 'bg-white text-amber-600 shadow-sm dark:bg-zinc-800 dark:text-amber-400' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"><i data-lucide="list" class="h-4 w-4"></i></a>
                            </div>
                        </form>
                    </div>

                    @if($layout === 'grid')
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            @forelse($products as $product)
                                @php($defaultVariant = $product->variants->first())
                                <article class="group flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70 dark:hover:border-zinc-700 dark:hover:shadow-xl">
                                    <a class="relative aspect-square overflow-hidden bg-zinc-100 dark:bg-zinc-950" href="{{ route('storefront.products.show', $product) }}">
                                        <img class="h-full w-full object-cover transition duration-500 group-hover:scale-105" src="{{ $product->images->first()?->path }}" alt="{{ $product->title }}">
                                        @if($product->compare_at_price)<span class="absolute left-3 top-3 rounded-full bg-red-500/90 px-2 py-0.5 text-[10px] font-bold uppercase text-white">SAVE {{ round((1 - $product->base_price / $product->compare_at_price) * 100) }}%</span>@endif
                                        <span class="absolute right-3 top-3 flex items-center gap-1 rounded-full bg-white/90 px-2 py-0.5 font-mono text-[11px] text-zinc-600 shadow-sm dark:bg-zinc-950/80 dark:text-zinc-300"><i data-lucide="star" class="h-3 w-3 fill-amber-400 text-amber-500 dark:text-amber-400"></i>4.8</span>
                                    </a>
                                    <div class="flex flex-1 flex-col justify-between space-y-3 p-4">
                                        <div><div class="flex items-center justify-between gap-2"><span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $product->brand }}</span><span class="truncate font-mono text-[10px] text-emerald-600 dark:text-emerald-400">SKU: {{ $defaultVariant?->sku }}</span></div><a href="{{ route('storefront.products.show', $product) }}"><h2 class="mt-0.5 truncate text-sm font-bold text-zinc-900 transition group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-300">{{ $product->title }}</h2></a><p class="mt-0.5 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $product->subtitle }}</p></div>
                                        @if($product->variants->count() > 1)<div class="flex items-center gap-1.5 pt-1">@foreach($product->variants as $index => $variant)<a href="{{ route('storefront.products.show', [$product, 'variant' => $variant->id]) }}" title="{{ $variant->color_name }}" class="h-4 w-4 rounded-full border {{ $index === 0 ? 'border-zinc-900 ring-2 ring-amber-400 ring-offset-2 ring-offset-white dark:border-white dark:ring-offset-zinc-900' : 'border-zinc-300 opacity-70 hover:opacity-100 dark:border-zinc-700' }}" style="background:{{ $variant->color_hex }}"></a>@endforeach<span class="ml-1 font-mono text-[10px] text-zinc-500 dark:text-zinc-400">{{ $product->variants->count() }} finishes</span></div>@endif
                                        @if($defaultVariant)<div class="mt-auto flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800"><div><b class="text-sm text-zinc-900 dark:text-zinc-100">${{ number_format($defaultVariant->price / 100, 2) }}</b><del class="ml-1.5 text-xs text-zinc-400">${{ number_format($defaultVariant->compare_at_price / 100, 2) }}</del></div><form method="POST" action="{{ route('cart.items.store') }}">@csrf<input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}"><input type="hidden" name="quantity" value="1"><button title="Add to Cart" aria-label="Add {{ $product->title }} to cart" class="rounded-lg bg-zinc-100 p-2 text-zinc-700 transition hover:bg-amber-400 hover:text-zinc-950 dark:bg-zinc-800 dark:text-zinc-200"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i></button></form></div>@endif
                                    </div>
                                </article>
                            @empty
                                <div class="col-span-full rounded-2xl border border-zinc-200 bg-white p-12 text-center text-zinc-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-400">No products match your active filter criteria. <a class="text-amber-600 dark:text-amber-300" href="{{ route('storefront.index') }}">Reset filters</a></div>
                            @endforelse
                        </div>
                    @else
                        <div class="space-y-4">
                            @forelse($products as $product)
                                @php($defaultVariant = $product->variants->first())
                                <article class="group flex flex-col gap-5 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70 dark:hover:border-zinc-700 sm:flex-row"><a class="aspect-square w-full shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-950 sm:w-48" href="{{ route('storefront.products.show', $product) }}"><img class="h-full w-full object-cover transition duration-500 group-hover:scale-105" src="{{ $product->images->first()?->path }}" alt="{{ $product->title }}"></a><div class="flex flex-1 flex-col justify-between space-y-2"><div><div class="flex items-center justify-between"><span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $product->brand }}</span><span class="flex items-center gap-1 font-mono text-xs text-zinc-600 dark:text-zinc-300"><i data-lucide="star" class="h-3 w-3 fill-amber-400 text-amber-500 dark:text-amber-400"></i>4.8</span></div><a href="{{ route('storefront.products.show', $product) }}"><h2 class="mt-0.5 font-serif text-base font-bold text-zinc-900 transition group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-300">{{ $product->title }}</h2></a><p class="mt-1 text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $product->description }}</p></div>@if($defaultVariant)<div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800"><div><b class="text-base text-zinc-900 dark:text-white">${{ number_format($defaultVariant->price / 100, 2) }}</b><del class="ml-2 text-xs text-zinc-400">${{ number_format($defaultVariant->compare_at_price / 100, 2) }}</del></div><form method="POST" action="{{ route('cart.items.store') }}">@csrf<input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}"><input type="hidden" name="quantity" value="1"><button class="flex items-center gap-1.5 rounded-lg bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-amber-500 hover:text-zinc-950 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i>Add to Cart</button></form></div>@endif</div></article>
                            @empty
                                <div class="rounded-2xl border border-zinc-200 bg-white p-12 text-center text-zinc-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-400">No products match your active filter criteria. <a class="text-amber-600 dark:text-amber-300" href="{{ route('storefront.index') }}">Reset filters</a></div>
                            @endforelse
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-zinc-200 pt-6 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"><span>Page 1 of 1 ({{ $products->count() }} products displayed)</span><div class="flex gap-2"><span class="rounded border border-zinc-200 bg-white px-3 py-1.5 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600">Previous</span><span class="rounded bg-zinc-900 px-3 py-1.5 font-bold text-white dark:bg-zinc-100 dark:text-zinc-950">1</span><span class="rounded border border-zinc-200 bg-white px-3 py-1.5 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600">Next</span></div></div>
                </section>
            </div>
            <x-storefront.footer />
        </main>
    </div>
</x-layouts.app>
