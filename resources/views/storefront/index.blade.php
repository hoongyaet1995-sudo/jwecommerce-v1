@php
    $collectionTitle = $collection?->title;
    $listingTitle = $collectionTitle ?? ($category ? ($categories->firstWhere('slug', $category)?->name ?? 'Category Collection') : 'All '.$shopBrandName.' Collections');
    $listingEyebrow = 'Catalog & Product Listing'
        . ($collection ? ' • ' . ($collection->badge ?: $collection->title) : '')
        . ' • ' . $products->total() . ' Items Found';
    $listingDescription = $collection?->subtitle ?: 'Precision engineered audio systems, ergonomic desk architectures, titanium watches, and weatherproof carry systems.';
    $filterQuery = request()->except('page');
    $filterUrl = function (array $remove = [], array $replace = []) use ($listingRouteName, $listingRouteParameters, $filterQuery) {
        $query = $filterQuery;
        foreach ($remove as $key) unset($query[$key]);
        $query = array_replace($query, $replace);
        return route($listingRouteName, array_merge($listingRouteParameters, $query));
    };
    $hasActiveFilters = $category || $filters['priceRequested'] || $filters['colors'] !== [] || $filters['materials'] !== [] || $filters['minRating'] > 0 || $filters['inStock'];
@endphp
<x-layouts.app :title="$listingTitle . ' | ATELIER'">
    <div class="min-h-screen bg-zinc-50 font-sans text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <x-storefront.nav active="plp" />

        <main class="pb-20">
        <section class="border-b border-zinc-200 bg-zinc-50 px-4 py-10 sm:px-6 lg:px-8 dark:border-zinc-900 dark:bg-zinc-950">
                <div class="mx-auto max-w-7xl">
                    <p class="font-mono text-xs uppercase tracking-widest text-amber-600 dark:text-amber-400">{{ $listingEyebrow }}</p>
                    <h1 class="mt-1 font-serif text-3xl font-bold text-zinc-900 dark:text-white">{{ $listingTitle }}</h1>
                    <p class="mt-1 max-w-xl text-xs text-zinc-600 dark:text-zinc-400">{{ $listingDescription }}</p>
                </div>
            </section>

            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 pt-8 sm:px-6 lg:grid-cols-12 lg:px-8">
                <aside class="hidden lg:block lg:col-span-3">
                    <x-storefront.plp-filters :categories="$categories" :search="$search" :category="$category" :sort="$sort" :layout="$layout" :filters="$filters" :facets="$facets" :route-name="$listingRouteName" :route-parameters="$listingRouteParameters" />
                </aside>

                <section x-data="{ mobileFilterOpen: false }" class="space-y-6 lg:col-span-9">
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-3.5 shadow-sm dark:border-zinc-800/90 dark:bg-zinc-900/60">
                        <button type="button" @click="mobileFilterOpen = true" class="lg:hidden flex items-center gap-1.5 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-medium text-zinc-800 dark:text-zinc-200 cursor-pointer"><i data-lucide="sliders-horizontal" class="h-3.5 w-3.5 text-amber-500 dark:text-amber-400"></i><span>Filters ({{ ($category ? 1 : 0) + ($filters['priceRequested'] ? 1 : 0) + count($filters['colors']) + count($filters['materials']) + ($filters['inStock'] ? 1 : 0) + ($filters['minRating'] > 0 ? 1 : 0) }})</span></button>
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">Showing <b class="text-zinc-900 dark:text-zinc-200">{{ $products->total() }}</b> items</span>
                        <form method="GET" data-catalog-toolbar-sort class="flex items-center gap-3">
                            @foreach(request()->except(['sort', 'page']) as $key => $value)
                                @if(is_array($value))@foreach($value as $entry)<input type="hidden" name="{{ $key }}[]" value="{{ $entry }}">@endforeach
                                @elseif(filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                            @endforeach
                            <label class="flex items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-400">Sort by:
                                <select name="sort" onchange="this.form.submit()" class="rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs text-zinc-800 outline-none focus:border-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:focus:border-amber-400">
                                    <option value="">Featured Curations</option><option value="price_asc" @selected($sort === 'price_asc')>Price: Low to High</option><option value="price_desc" @selected($sort === 'price_desc')>Price: High to Low</option><option value="rating" @selected($sort === 'rating')>Customer Rating</option><option value="newest" @selected($sort === 'newest')>Newest Releases</option>
                                </select>
                            </label>
                            <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-100 p-0.5 dark:border-zinc-800 dark:bg-zinc-900">
                                <a title="Grid View" aria-label="Grid View" href="{{ route($listingRouteName, array_merge($listingRouteParameters, array_filter(request()->except('layout') + ['layout' => 'grid']))) }}" class="rounded-md p-1.5 {{ $layout === 'grid' ? 'bg-white text-amber-600 shadow-sm dark:bg-zinc-800 dark:text-amber-400' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"><i data-lucide="grid" class="h-4 w-4"></i></a>
                                <a title="List View" aria-label="List View" href="{{ route($listingRouteName, array_merge($listingRouteParameters, array_filter(request()->except('layout') + ['layout' => 'list']))) }}" class="rounded-md p-1.5 {{ $layout === 'list' ? 'bg-white text-amber-600 shadow-sm dark:bg-zinc-800 dark:text-amber-400' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"><i data-lucide="list" class="h-4 w-4"></i></a>
                            </div>
                        </form>
                    </div>

                    <div x-show="mobileFilterOpen" x-cloak class="fixed inset-0 z-50 lg:hidden flex justify-end">
                        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="mobileFilterOpen = false"></div>
                        <div class="relative w-full max-w-xs sm:max-w-sm bg-white dark:bg-zinc-900 h-full shadow-2xl flex flex-col z-10 border-l border-zinc-200 dark:border-zinc-800">
                            <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between"><div class="flex items-center gap-2"><i data-lucide="sliders-horizontal" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i><h3 class="text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider">Filters Matrix</h3></div><div class="flex items-center gap-2"><a href="{{ route($listingRouteName, $listingRouteParameters) }}" class="text-[11px] text-zinc-500 hover:text-amber-600 dark:text-zinc-400 dark:hover:text-amber-300 flex items-center gap-1 cursor-pointer transition-colors"><i data-lucide="rotate-ccw" class="w-3 h-3"></i><span>Reset</span></a><button type="button" @click="mobileFilterOpen = false" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer" aria-label="Close filters drawer"><i data-lucide="x" class="w-4 h-4"></i></button></div></div>
                            <div class="p-5 overflow-y-auto flex-1"><x-storefront.plp-filters :mobile="true" :categories="$categories" :search="$search" :category="$category" :sort="$sort" :layout="$layout" :filters="$filters" :facets="$facets" :route-name="$listingRouteName" :route-parameters="$listingRouteParameters" /></div>
                            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex items-center gap-3"><a href="{{ route($listingRouteName, $listingRouteParameters) }}" class="flex-1 py-2.5 px-4 rounded-xl border border-zinc-200 dark:border-zinc-800 text-xs font-semibold text-center text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors cursor-pointer">Reset All</a><button type="button" @click="mobileFilterOpen = false" class="flex-1 py-2.5 px-4 rounded-xl bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-950 text-xs font-bold hover:bg-amber-400 dark:hover:bg-amber-400 transition-colors shadow-sm cursor-pointer">Show ({{ $products->total() }})</button></div>
                        </div>
                    </div>

                    @if($hasActiveFilters)<div class="flex flex-wrap items-center gap-2"><span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">Active Filters:</span>@if($category)<a href="{{ $filterUrl(['category']) }}" class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 px-2.5 py-1 rounded-full shadow-sm">Category: {{ $categories->firstWhere('slug', $category)?->name ?? $category }}<i data-lucide="x" class="w-3 h-3"></i></a>@endif@if($filters['priceRequested'])<a href="{{ $filterUrl(['min_price', 'max_price']) }}" class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 px-2.5 py-1 rounded-full shadow-sm">Price: @storefrontMoney($filters['minPrice']) – @storefrontMoney($filters['maxPrice'])<i data-lucide="x" class="w-3 h-3"></i></a>@endif@foreach($filters['colors'] as $selectedColor)@php($remaining = array_values(array_diff($filters['colors'], [$selectedColor])))<a href="{{ $filterUrl(['color'], $remaining ? ['color' => $remaining] : []) }}" class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 px-2.5 py-1 rounded-full shadow-sm">Color: {{ $selectedColor }}<i data-lucide="x" class="w-3 h-3"></i></a>@endforeach@foreach($filters['materials'] as $selectedMaterial)@php($remaining = array_values(array_diff($filters['materials'], [$selectedMaterial])))<a href="{{ $filterUrl(['material'], $remaining ? ['material' => $remaining] : []) }}" class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 px-2.5 py-1 rounded-full shadow-sm">Material: {{ $selectedMaterial }}<i data-lucide="x" class="w-3 h-3"></i></a>@endforeach@if($filters['minRating'] > 0)<a href="{{ $filterUrl(['min_rating']) }}" class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 px-2.5 py-1 rounded-full shadow-sm">Rating: {{ $filters['minRating'] }}+ Stars<i data-lucide="x" class="w-3 h-3"></i></a>@endif@if($filters['inStock'])<a href="{{ $filterUrl(['in_stock']) }}" class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 px-2.5 py-1 rounded-full shadow-sm">In Stock Only<i data-lucide="x" class="w-3 h-3"></i></a>@endif<a href="{{ route($listingRouteName, $listingRouteParameters) }}" class="text-xs text-amber-600 dark:text-amber-400 hover:underline ml-2 cursor-pointer font-medium">Clear all</a></div>@endif

                    @if($layout === 'grid')
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            @forelse($products as $product)
                                @php($defaultVariant = $product->variants->first())
                                @php($requiresQuickAdd = $product->variants->count() > 1)
                                <article data-storefront-product-card class="group flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70 dark:hover:border-zinc-700 dark:hover:shadow-xl">
                                    <a class="relative aspect-square overflow-hidden bg-zinc-100 dark:bg-zinc-950" href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}">
                                        <img data-card-image class="h-full w-full object-cover transition duration-500 group-hover:scale-105" src="{{ $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->title }}">
                                        @if($product->compare_at_price)<span class="absolute left-3 top-3 rounded-full bg-red-500/90 px-2 py-0.5 text-[10px] font-bold uppercase text-white">SAVE {{ round((1 - $product->base_price / $product->compare_at_price) * 100) }}%</span>@endif
                                    </a>
                                    <div class="flex flex-1 flex-col justify-between space-y-3 p-4">
                                        <div><div class="flex items-center justify-between gap-2"><span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $product->brand }}</span><span class="truncate font-mono text-[10px] text-emerald-600 dark:text-emerald-400">SKU: {{ $defaultVariant?->sku }}</span></div><a href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}"><h2 class="mt-0.5 truncate text-sm font-bold text-zinc-900 transition group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-300">{{ $product->title }}</h2></a><p class="mt-0.5 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $product->subtitle }}</p></div>
                                        <x-storefront.product-card-variant-preview :product="$product" :variants="$product->variants" :images="$product->images" />
                                        @if($defaultVariant)<div class="mt-auto flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800"><div><b data-card-price class="text-sm text-zinc-900 dark:text-zinc-100">@storefrontMoney($defaultVariant->price)</b><del data-card-compare-at class="ml-1.5 text-xs text-zinc-400">@storefrontMoney($defaultVariant->compare_at_price)</del></div>@if($requiresQuickAdd)<input data-card-variant-input type="hidden" value="{{ $defaultVariant->id }}"><button type="button" data-quick-add-trigger data-quick-add-product-id="{{ $product->id }}" title="Select Options &amp; Quick Add" aria-label="Select options for {{ $product->title }}" class="rounded-lg bg-zinc-100 p-2 text-zinc-700 transition hover:bg-amber-400 hover:text-zinc-950 dark:bg-zinc-800 dark:text-zinc-200"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i></button>@else<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-card-variant-input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}"><input type="hidden" name="quantity" value="1"><button title="Add to Cart" aria-label="Add {{ $product->title }} to cart" @disabled(! $defaultVariant->enabled || $defaultVariant->stock < 1) class="rounded-lg bg-zinc-100 p-2 text-zinc-700 transition hover:bg-amber-400 hover:text-zinc-950 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-zinc-800 dark:text-zinc-200"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i></button></form>@endif</div>@endif
                                    </div>
                                </article>
                            @empty
                                <div class="col-span-full rounded-2xl border border-zinc-200 bg-white p-12 text-center text-zinc-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-400">No products match your active filter criteria. <a class="text-amber-600 dark:text-amber-300" href="{{ route($listingRouteName, $listingRouteParameters) }}">Reset filters</a></div>
                            @endforelse
                        </div>
                    @else
                        <div class="space-y-4">
                            @forelse($products as $product)
                                @php($defaultVariant = $product->variants->first())
                                @php($requiresQuickAdd = $product->variants->count() > 1)
                                <article data-storefront-product-card class="group flex flex-col gap-5 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70 dark:hover:border-zinc-700 sm:flex-row"><a class="aspect-square w-full shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-950 sm:w-48" href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}"><img data-card-image class="h-full w-full object-cover transition duration-500 group-hover:scale-105" src="{{ $product->images->first()?->path }}" alt="{{ $product->title }}"></a><div class="flex flex-1 flex-col justify-between space-y-2"><div><div class="flex items-center justify-between"><span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $product->brand }}</span></div><a href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}"><h2 class="mt-0.5 font-serif text-base font-bold text-zinc-900 transition group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-300">{{ $product->title }}</h2></a><p class="mt-1 text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $product->description }}</p></div><x-storefront.product-card-variant-preview :product="$product" :variants="$product->variants" :images="$product->images" />@if($defaultVariant)<div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800"><div><b data-card-price class="text-base text-zinc-900 dark:text-white">@storefrontMoney($defaultVariant->price)</b><del data-card-compare-at class="ml-2 text-xs text-zinc-400">@storefrontMoney($defaultVariant->compare_at_price)</del></div>@if($requiresQuickAdd)<input data-card-variant-input type="hidden" value="{{ $defaultVariant->id }}"><button type="button" data-quick-add-trigger data-quick-add-product-id="{{ $product->id }}" class="flex items-center gap-1.5 rounded-lg bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-amber-500 hover:text-zinc-950 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i>Select Options</button>@else<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-card-variant-input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}"><input type="hidden" name="quantity" value="1"><button @disabled(! $defaultVariant->enabled || $defaultVariant->stock < 1) class="flex items-center gap-1.5 rounded-lg bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-amber-500 hover:text-zinc-950 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i>Add to Cart</button></form>@endif</div>@endif</div></article>
                            @empty
                                <div class="rounded-2xl border border-zinc-200 bg-white p-12 text-center text-zinc-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-400">No products match your active filter criteria. <a class="text-amber-600 dark:text-amber-300" href="{{ route($listingRouteName, $listingRouteParameters) }}">Reset filters</a></div>
                            @endforelse
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-zinc-200 pt-6 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"><span>Page {{ $products->currentPage() }} of {{ max(1, $products->lastPage()) }} ({{ $products->total() }} products displayed)</span><div class="flex gap-2">@if($products->onFirstPage())<span class="rounded border border-zinc-200 bg-white px-3 py-1.5 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600">Previous</span>@else<a href="{{ $products->previousPageUrl() }}" class="rounded border border-zinc-200 bg-white px-3 py-1.5 hover:text-amber-600 dark:border-zinc-800 dark:bg-zinc-900">Previous</a>@endif<span class="rounded bg-zinc-900 px-3 py-1.5 font-bold text-white dark:bg-zinc-100 dark:text-zinc-950">{{ $products->currentPage() }}</span>@if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}" class="rounded border border-zinc-200 bg-white px-3 py-1.5 hover:text-amber-600 dark:border-zinc-800 dark:bg-zinc-900">Next</a>@else<span class="rounded border border-zinc-200 bg-white px-3 py-1.5 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600">Next</span>@endif</div></div>
                </section>
            </div>
            @foreach($products as $product)
                @if($product->variants->count() > 1)
                    <x-storefront.quick-add-variant-modal :product="$product" :variants="$product->variants" :images="$product->images" />
                @endif
            @endforeach
        </main>
        <x-storefront.footer />
    </div>
</x-layouts.app>
