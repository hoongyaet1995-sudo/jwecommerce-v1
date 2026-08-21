<x-layouts.app title="{{ $product->title }} | JW Atelier">
    @php
        $cartEnabled = $selectedVariant && $selectedVariant->stock > 0;
        $productUrl = route('storefront.products.show', $product);
        $price = $selectedVariant?->price ?? $product->base_price;
        $compareAt = $selectedVariant?->compare_at_price ?? $product->compare_at_price;
    @endphp

    <div class="min-h-screen bg-zinc-50 font-sans text-zinc-900 transition-colors duration-200 dark:bg-zinc-950 dark:text-zinc-100">
        <x-storefront.nav active="pdp" :pdp-url="$productUrl" />

        <div class="border-b border-zinc-200 bg-zinc-100/60 px-4 py-3 sm:px-6 lg:px-8 dark:border-zinc-900 dark:bg-zinc-900/40">
            <div class="mx-auto flex max-w-7xl items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <div class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                    <a href="{{ route('storefront.home') }}" class="hover:text-zinc-900 dark:hover:text-zinc-200">Atelier</a>
                    <span>/</span>
                    <a href="{{ route('storefront.index', ['category' => $product->category?->slug]) }}" class="hover:text-zinc-900 dark:hover:text-zinc-200">{{ strtoupper($product->category?->name ?? 'Studio') }}</a>
                    <span>/</span>
                    <span class="max-w-xs truncate font-medium text-zinc-900 dark:text-zinc-200">{{ $product->title }}</span>
                </div>
                <span class="hidden font-mono text-[11px] text-zinc-500 sm:block">In-Stock • Dispatches Today</span>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 pb-32 pt-8 sm:px-6 lg:px-8">

            <section class="grid gap-12 lg:grid-cols-12">
                <div class="space-y-4 lg:col-span-7">
                    <div class="group relative aspect-[4/3] overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                        @if($selectedImage)
                            <img src="{{ $selectedImage->path }}" alt="{{ $selectedImage->alt ?: $product->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                        @else
                            <div class="grid h-full place-items-center bg-zinc-100 font-serif text-4xl text-zinc-400 dark:bg-zinc-900 dark:text-zinc-600">JW / ATELIER</div>
                        @endif
                        <span class="absolute left-4 top-4 rounded-full border border-zinc-200/80 bg-white/90 px-3 py-1 font-mono text-xs text-amber-600 shadow-sm backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/80 dark:text-amber-300">{{ strtoupper($product->brand ?: 'JW Atelier') }}</span>
                        <div class="absolute right-4 top-4 flex items-center gap-2">
                            @if(auth()->check() && auth()->user()->customer)
                                <form method="POST" action="{{ route('customer.wishlist.store', $product) }}">
                                    @csrf
                                    <button type="submit" data-pdp-wishlist aria-pressed="{{ auth()->user()->customer->wishlistItems()->where('product_id', $product->id)->exists() ? 'true' : 'false' }}" title="Save to wishlist" class="rounded-full border border-zinc-200 bg-white/80 p-2 text-zinc-700 shadow-sm backdrop-blur-md transition-colors hover:text-rose-500 dark:border-zinc-800 dark:bg-zinc-950/70 dark:text-zinc-300"><i data-lucide="heart" class="h-4 w-4"></i><span class="sr-only">Save to wishlist</span></button>
                                </form>
                            @else
                                <a href="{{ route('customer.login') }}" data-pdp-wishlist aria-pressed="false" title="Sign in to save to wishlist" class="rounded-full border border-zinc-200 bg-white/80 p-2 text-zinc-700 shadow-sm backdrop-blur-md transition-colors hover:text-rose-500 dark:border-zinc-800 dark:bg-zinc-950/70 dark:text-zinc-300"><i data-lucide="heart" class="h-4 w-4"></i><span class="sr-only">Sign in to save to wishlist</span></a>
                            @endif
                        </div>
                    </div>
                    @if($images->isNotEmpty())
                        <div class="mt-4 grid grid-cols-4 gap-3">
                            @foreach($images as $imageIndex => $image)
                                <a href="{{ $productUrl }}?variant={{ $selectedVariant?->id }}&amp;image={{ $imageIndex }}" class="relative aspect-square overflow-hidden rounded-xl border bg-zinc-100 transition-all dark:bg-zinc-900 {{ $selectedImage?->id === $image->id ? 'border-amber-500 ring-2 ring-amber-500/40 dark:border-amber-400 dark:ring-amber-400/40 shadow-sm' : 'border-zinc-200 opacity-70 hover:opacity-100 dark:border-zinc-800' }}">
                                    <img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" class="aspect-square w-full object-cover" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <section class="mt-6 space-y-3 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800/80 dark:bg-zinc-900/60">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-800 dark:text-zinc-300">Signature Craftsmanship Highlights</h2>
                        <ul class="grid gap-2 text-xs text-zinc-700 dark:text-zinc-300 sm:grid-cols-2">
                            @forelse(array_slice($product->features ?? [], 0, 6) as $feature)
                                <li class="flex items-start gap-2"><i data-lucide="check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500 dark:text-emerald-400"></i><span>{{ $feature }}</span></li>
                            @empty
                                <li class="flex items-start gap-2"><i data-lucide="check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400"></i><span>Precision-crafted studio object.</span></li>
                            @endforelse
                        </ul>
                    </section>
                </div>

                <section class="space-y-6 lg:col-span-5">
                    <div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-amber-600 dark:text-amber-400">{{ $product->brand ?: 'JW Atelier' }}</p>
                        </div>
                        @if($selectedVariant)<span class="rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 font-mono text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">{{ $selectedVariant->sku }}</span>@endif
                    </div>
                    <h1 class="mt-1 font-serif text-2xl font-bold text-zinc-900 sm:text-3xl dark:text-white">{{ $product->title }}</h1>
                    <p class="mt-1 text-xs text-zinc-600 sm:text-sm dark:text-zinc-400">{{ $product->subtitle }}</p>
                    <div class="mt-3 flex items-center gap-3"><span class="flex items-center gap-1 text-amber-500 dark:text-amber-400">@for($star = 0; $star < 5; $star++)<i data-lucide="star" class="h-3.5 w-3.5 fill-current"></i>@endfor</span><span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-300">4.9</span><span class="text-xs text-zinc-400">•</span><span class="cursor-pointer text-xs text-zinc-500 underline dark:text-zinc-400">142 Verified Studio Reviews</span></div>
                    </div>

                    <div class="space-y-2 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/90">
                        <div class="flex items-baseline gap-3">
                            <strong class="font-serif text-3xl font-bold text-zinc-900 dark:text-white">${{ number_format($price / 100, 2) }}</strong>
                            @if($compareAt)<del class="text-sm text-zinc-400 dark:text-zinc-500">${{ number_format($compareAt / 100, 2) }}</del><span class="rounded bg-amber-400 px-2 py-0.5 text-xs font-bold uppercase text-zinc-950">SAVE ${{ number_format(($compareAt - $price) / 100, 2) }}</span>@endif
                        </div>
                        <p class="flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-400"><i data-lucide="zap" class="h-3.5 w-3.5 text-amber-500 dark:text-amber-400"></i>Or 4 interest-free payments of <strong>${{ number_format($price / 400, 2) }}</strong> with Klarna or Apple Pay.</p>
                    </div>

                    <form method="POST" action="{{ route('cart.items.store') }}">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}" />
                        <label class="flex items-center justify-between text-xs">
                            <span class="font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Finish: <strong class="font-sans text-amber-600 dark:text-amber-300">{{ $selectedVariant?->color_name ?: 'Studio standard' }}</strong></span>
                            <span class="font-mono text-[11px] text-emerald-600 dark:text-emerald-400">✓ {{ $selectedVariant?->stock ?? 0 }} Units In Stock</span>
                        </label>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach($product->variants as $variant)
                                <a href="{{ $productUrl }}?variant={{ $variant->id }}" class="flex flex-col justify-between rounded-xl border p-3 text-left transition-all {{ $selectedVariant?->id === $variant->id ? 'border-amber-500 bg-amber-500/10 text-zinc-900 ring-1 ring-amber-500/40 dark:border-amber-400 dark:bg-amber-400/10 dark:text-white dark:ring-amber-400/40 shadow-sm' : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-400 dark:hover:bg-zinc-900' }} {{ $variant->stock < 1 ? 'opacity-50' : '' }}">
                                    <span class="mb-2 flex items-center justify-between"><span class="h-4 w-4 rounded-full border border-zinc-300 shadow dark:border-white/40" style="background-color:{{ $variant->color_hex ?: '#71717a' }}"></span>@if($selectedVariant?->id === $variant->id)<i data-lucide="check" class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400"></i>@endif</span>
                                    <span class="truncate text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $variant->color_name ?: $variant->title }}</span>
                                    <span class="mt-1 font-mono text-[11px] text-zinc-500 dark:text-zinc-400">${{ number_format($variant->price / 100, 0) }}{{ $variant->stock < 1 ? ' · Sold out' : '' }}</span>
                                </a>
                            @endforeach
                        </div>
                        <p class="mt-6 font-mono text-sm font-bold uppercase tracking-wide text-zinc-800 dark:text-white">Configuration</p>
                        <span class="mt-3 inline-block rounded-lg border border-zinc-200 bg-zinc-100 px-4 py-2 font-mono text-sm text-zinc-800 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">{{ $selectedVariant?->material ?: 'Standard atelier specification' }}</span>
                        <div class="mt-8 flex gap-3">
                            <input name="quantity" type="number" value="1" min="1" max="{{ max(1, $selectedVariant?->stock ?? 1) }}" class="w-28 rounded-xl border border-zinc-200 bg-white px-4 py-4 text-center font-mono text-zinc-900 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-white" />
                            <button @disabled(!$cartEnabled) class="flex-1 rounded-xl bg-zinc-900 px-5 py-4 text-base font-black text-white transition hover:bg-amber-400 hover:text-zinc-950 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400">{{ $cartEnabled ? 'Add to Cart · $'.number_format($price / 100, 2) : 'Sold out' }}</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('cart.items.store') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}" />
                        <input type="hidden" name="quantity" value="1" />
                        <input type="hidden" name="checkout" value="1" />
                        <button @disabled(!$cartEnabled) class="w-full rounded-xl bg-amber-400 px-5 py-4 font-black text-zinc-950 disabled:opacity-40">Instant Buy with Simulated 1-Click</button>
                    </form>
                    <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-3.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                        <p class="font-semibold text-emerald-600 dark:text-emerald-400">Complimentary Express Shipping Included</p>
                        <p class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400">Order in the next 3 hrs 12 mins for dispatch today. Estimated arrival: 3–5 business days.</p>
                    </div>
                    <details open class="mt-7 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <summary class="cursor-pointer px-5 py-5 font-mono text-sm font-bold uppercase tracking-wide text-zinc-800 dark:text-zinc-200">Technical Specifications Matrix</summary>
                        <div class="border-t border-zinc-200 p-5 dark:border-zinc-800">
                            <p class="mb-4 font-mono text-xs uppercase tracking-wide text-amber-600 dark:text-amber-300">Specification matrix</p>
                            @forelse($product->features ?? [] as $feature)
                                <div class="border-b border-zinc-200 py-3 text-sm text-zinc-700 last:border-0 dark:border-zinc-800 dark:text-zinc-300">{{ $feature }}</div>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Studio standard configuration.</p>
                            @endforelse
                        </div>
                    </details>
                    <details class="mt-3 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <summary class="flex cursor-pointer items-center justify-between px-5 py-5 text-xs font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Shipping &amp; 30-Day Return Guarantee <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="space-y-2 border-t border-zinc-200 p-5 pt-4 text-xs text-zinc-700 dark:border-zinc-800/80 dark:text-zinc-300"><p>Every piece is hand-inspected and shipped in custom protective recycled molded fiber packaging.</p><ul class="list-disc space-y-1 pl-4 text-zinc-500 dark:text-zinc-400"><li>Free 30-day in-home trial with prepaid return label.</li><li>Full refund issued within 48 hours of warehouse scan.</li></ul></div>
                    </details>
                    <details class="mt-3 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <summary class="flex cursor-pointer items-center justify-between px-5 py-5 text-xs font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Verified Studio Reviews (2) <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="space-y-3 border-t border-zinc-200 p-5 pt-4 dark:border-zinc-800/80"><div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs dark:border-zinc-800/80 dark:bg-zinc-950"><div class="flex items-center justify-between"><strong class="text-zinc-900 dark:text-zinc-100">Studio Member</strong><span class="font-mono text-[10px] text-zinc-500">Verified buyer</span></div><p class="mt-2 text-zinc-600 dark:text-zinc-300">Precision materials and thoughtful performance.</p></div></div>
                    </details>
                </section>
            </section>

            <section class="mt-20 border-t border-zinc-200 pt-12 dark:border-zinc-900">
                <p class="font-mono text-sm font-bold uppercase tracking-[0.16em] text-amber-600 dark:text-amber-300">Modular Synergy</p>
                <h2 class="mt-2 font-serif text-4xl font-black text-zinc-900 dark:text-white">Frequently Paired With This Model</h2>
                <div class="mt-7 grid gap-5 md:grid-cols-2">
                    @foreach($companions as $companion)
                        @php($companionVariant = $companion->variants->first())
                        <article class="min-w-0 flex items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition-all hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:border-zinc-700">
                            @if($companion->images->first())<img src="{{ $companion->images->first()->path }}" alt="{{ $companion->title }}" class="h-24 w-24 rounded-xl object-cover" />@endif
                            <div class="min-w-0 flex-1"><p class="font-mono text-[10px] uppercase text-zinc-500 dark:text-zinc-400">{{ $companion->brand }}</p><a href="{{ route('storefront.products.show', $companion) }}" class="mt-1 block truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $companion->title }}</a><p class="mt-1 text-xs font-bold text-amber-600 dark:text-amber-300">${{ number_format(($companionVariant?->price ?? $companion->base_price) / 100, 2) }}</p></div>
                            @if($companionVariant)<form method="POST" action="{{ route('cart.items.store') }}" class="shrink-0">@csrf<input type="hidden" name="variant_id" value="{{ $companionVariant->id }}"><input type="hidden" name="quantity" value="1"><button class="flex items-center gap-1 rounded-lg bg-zinc-100 px-3 py-2 text-xs font-semibold text-zinc-800 transition-colors hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i>Add Pair</button></form>@endif
                        </article>
                    @endforeach
                </div>
            </section>
        </main>
        <x-storefront.footer />
    </div>
    <div data-pdp-sticky-purchase class="fixed bottom-0 left-0 right-0 z-30 border-t border-zinc-200 bg-white/95 px-4 py-3 shadow-2xl backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/95 sm:px-6">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">@if($selectedImage)<img src="{{ $selectedImage->path }}" alt="" class="hidden h-10 w-10 shrink-0 rounded-lg object-cover sm:block">@endif<div class="min-w-0"><p class="truncate text-xs font-bold text-zinc-900 dark:text-white">{{ $product->title }}</p><p class="truncate text-[11px] text-zinc-500 dark:text-zinc-400">Selected: <strong class="text-amber-600 dark:text-amber-300">{{ $selectedVariant?->color_name ?: 'Studio standard' }}</strong> • SKU: {{ $selectedVariant?->sku }}</p></div></div>
            <div class="flex shrink-0 items-center gap-3"><div class="text-right"><p class="text-sm font-bold text-zinc-900 dark:text-white">${{ number_format($price / 100, 2) }}</p><p class="font-mono text-[10px] text-emerald-600 dark:text-emerald-400">Free Shipping</p></div><form method="POST" action="{{ route('cart.items.store') }}">@csrf<input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}"><input type="hidden" name="quantity" value="1"><button @disabled(!$cartEnabled) class="flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-amber-400 hover:text-zinc-950 disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400 sm:px-6 sm:text-sm"><i data-lucide="shopping-bag" class="h-4 w-4"></i>Add to Cart</button></form></div>
        </div>
    </div>
</x-layouts.app>
