<x-layouts.app title="{{ $product->title }}{{ filled($pdpBrand) ? ' | '.$pdpBrand : '' }}">
    @php
        $selectedAvailable = $selectedVariant ? app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($selectedVariant) : null;
        $cartEnabled = app(\App\Services\StorefrontVariantData::class)->canAdd($selectedVariant);
        $productUrl = route('storefront.products.show', ['handle' => $product->handle]);
        $fallbackImage = asset('images/image-not-found.png');
        $price = $selectedVariant?->price ?? $product->base_price;
        $compareAt = $selectedVariant?->compare_at_price ?? $product->compare_at_price;
        $hasSavings = $compareAt > $price;
        $pdpContent = $product->pdpContent;
        $pdpHighlights = collect($pdpContent?->highlights ?? [])
            ->filter(fn ($highlight) => filled(is_array($highlight) ? ($highlight['text'] ?? null) : $highlight))
            ->values();
        $pdpSpecifications = collect($pdpContent?->specifications ?? [])
            ->filter(fn ($specification) => filled(is_array($specification) ? ($specification['value'] ?? null) : $specification))
            ->values();
        $pdpShipping = $pdpContent && !$pdpContent->use_store_default_shipping ? ($pdpContent->shipping_policy ?? []) : [];
        $showShippingNotice = filled($pdpShipping['shippingBadgeText'] ?? null) || filled($pdpShipping['leadTimeText'] ?? null);
        $showShippingPolicy = filled($pdpShipping['policyTitle'] ?? null);
        $pdpReviews = $product->reviews;
        $pdpReviewCount = $pdpReviews->count();
        $pdpRating = $pdpReviewCount > 0 ? round((float) $pdpReviews->avg('rating'), 1) : null;
        $pdpFilledStars = min(5, max(0, (int) round($pdpRating ?? 0)));
        $showRating = $pdpReviewCount > 0;
        $pdpCustomAccordions = collect($pdpContent?->custom_accordions ?? [])
            ->filter(fn ($accordion) => is_array($accordion) && filled($accordion['title'] ?? null) && filled($accordion['content'] ?? null))
            ->values();
    @endphp

    <div class="min-h-screen bg-zinc-50 font-sans text-zinc-900 transition-colors duration-200 dark:bg-zinc-950 dark:text-zinc-100">
        <x-storefront.nav active="pdp" :pdp-url="$productUrl" />

        <div class="border-b border-zinc-200 bg-zinc-100/60 px-4 py-3 sm:px-6 lg:px-8 dark:border-zinc-900 dark:bg-zinc-900/40">
            <div class="mx-auto flex max-w-7xl items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <div class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                    <a href="{{ route('storefront.home') }}" class="hover:text-zinc-900 dark:hover:text-zinc-200">Atelier</a>
                    <span>/</span>
                    @if($product->category)
                        <a href="{{ route('storefront.categories.show', ['category' => $product->category->slug]) }}" class="hover:text-zinc-900 dark:hover:text-zinc-200">{{ strtoupper($product->category->name) }}</a>
                    @else
                        <span>{{ strtoupper('Studio') }}</span>
                    @endif
                    <span>/</span>
                    <span class="max-w-xs truncate font-medium text-zinc-900 dark:text-zinc-200">{{ $product->title }}</span>
                </div>
                @if($selectedVariant)<span data-pdp-breadcrumb-availability class="hidden font-mono text-[11px] text-zinc-500 sm:block">{{ $selectedAvailable > 0 ? 'In Stock ('.$selectedAvailable.' units)' : ($selectedAvailable === null ? 'Awaiting stock verification' : 'Out of Stock') }}</span>@endif
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 pb-32 pt-8 sm:px-6 lg:px-8">

            <section class="grid gap-12 lg:grid-cols-12">
                <div class="space-y-4 lg:col-span-7">
                    <div class="group relative aspect-[4/3] overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                        <img data-pdp-hero-image src="{{ $selectedImage?->path ?: $fallbackImage }}" alt="{{ $selectedImage?->alt ?: $product->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                        @if(filled($pdpBrand))<span class="absolute left-4 top-4 rounded-full border border-zinc-200/80 bg-white/90 px-3 py-1 font-mono text-xs text-amber-600 shadow-sm backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/80 dark:text-amber-300">{{ strtoupper($pdpBrand) }}</span>@endif
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
                                <a data-pdp-thumbnail data-pdp-thumbnail-switch data-pdp-image-path="{{ $image->path }}" data-pdp-image-alt="{{ $image->alt ?: $product->title }}" href="{{ $productUrl }}?variant={{ $selectedVariant?->id }}&amp;image={{ $imageIndex }}" class="relative aspect-square overflow-hidden rounded-xl border bg-zinc-100 transition-all dark:bg-zinc-900 {{ $selectedImage?->id === $image->id ? 'border-amber-500 ring-2 ring-amber-500/40 dark:border-amber-400 dark:ring-amber-400/40 shadow-sm' : 'border-zinc-200 opacity-70 hover:opacity-100 dark:border-zinc-800' }}">
                                    <img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" class="aspect-square w-full object-cover" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if($pdpHighlights->isNotEmpty())
                    <section class="mt-6 space-y-3 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800/80 dark:bg-zinc-900/60">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-800 dark:text-zinc-300">Signature Craftsmanship Highlights</h2>
                        <ul class="grid gap-2 text-xs text-zinc-700 dark:text-zinc-300 sm:grid-cols-2">
                            @foreach($pdpHighlights->take(6) as $highlight)
                                <li class="flex items-start gap-2"><i data-lucide="{{ \App\Services\ProductHighlightIcons::resolve(is_array($highlight) ? ($highlight['icon'] ?? null) : null) }}" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500 dark:text-emerald-400"></i><span>{{ is_array($highlight) ? $highlight['text'] : $highlight }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                    @endif
                </div>

                <section class="space-y-6 lg:col-span-5">
                    <div>
                    <div class="flex items-center justify-between">
                        <div>
                            @if(filled($pdpBrand))<p class="font-mono text-xs font-semibold uppercase tracking-widest text-amber-600 dark:text-amber-400">{{ $pdpBrand }}</p>@endif
                        </div>
                        @if($selectedVariant)<span data-pdp-sku class="rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 font-mono text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">{{ $selectedVariant->sku }}</span>@endif
                    </div>
                    <h1 class="mt-1 font-serif text-2xl font-bold text-zinc-900 sm:text-3xl dark:text-white">{{ $product->title }}</h1>
                    <p class="mt-1 text-xs text-zinc-600 sm:text-sm dark:text-zinc-400">{{ $product->subtitle }}</p>
                    @if($showRating)<div class="mt-3 flex items-center gap-3"><span class="flex items-center gap-1 text-amber-500 dark:text-amber-400">@for($star = 1; $star <= 5; $star++)<i data-pdp-rating-star="{{ $star <= $pdpFilledStars ? 'filled' : 'empty' }}" data-lucide="star" class="h-3.5 w-3.5 {{ $star <= $pdpFilledStars ? 'fill-current' : '' }}"></i>@endfor</span>@if($pdpRating !== null)<span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-300">{{ number_format((float) $pdpRating, 1) }}</span>@endif@if($pdpRating !== null && $pdpReviewCount > 0)<span class="text-xs text-zinc-400">•</span>@endif@if($pdpReviewCount > 0)<span class="cursor-pointer text-xs text-zinc-500 underline dark:text-zinc-400">{{ $pdpReviewCount }} Verified Studio Reviews</span>@endif</div>@endif
                    </div>

                    <div class="space-y-2 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/90">
                        <div class="flex items-baseline gap-3">
                            <strong data-pdp-price class="font-serif text-3xl font-bold text-zinc-900 dark:text-white">@storefrontMoney($price)</strong>
                            @if($hasSavings)<del data-pdp-compare-at class="text-sm text-zinc-400 dark:text-zinc-500">@storefrontMoney($compareAt)</del><span data-pdp-save class="rounded bg-amber-400 px-2 py-0.5 text-xs font-bold uppercase text-zinc-950">SAVE @storefrontMoney($compareAt - $price)</span>@else <del data-pdp-compare-at class="hidden text-sm text-zinc-400 dark:text-zinc-500"></del><span data-pdp-save class="hidden rounded bg-amber-400 px-2 py-0.5 text-xs font-bold uppercase text-zinc-950"></span>@endif
                        </div>
                        @if($pdpPaymentMethods !== [])<p class="flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-400"><i data-lucide="zap" class="h-3.5 w-3.5 text-amber-500 dark:text-amber-400"></i>Payment methods available at checkout: <strong>{{ implode(' · ', $pdpPaymentMethods) }}</strong>.</p>@endif
                    </div>

                    <form method="POST" action="{{ route('cart.items.store') }}">
                        @csrf
                        <input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}" />
                        <x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" />
                        <div class="mt-8 flex gap-3">
                            <input name="quantity" type="number" value="1" min="1" max="{{ max(1, $selectedAvailable ?? 1) }}" class="w-28 rounded-xl border border-zinc-200 bg-white px-4 py-4 text-center font-mono text-zinc-900 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-white" />
                            <button data-pdp-add-to-cart @disabled(!$cartEnabled) class="flex-1 rounded-xl bg-zinc-900 px-5 py-4 text-base font-black text-white transition hover:bg-amber-400 hover:text-zinc-950 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400">{{ $cartEnabled ? 'Add to Cart · '.app(\App\Support\StorefrontCurrencyContext::class)->format($price) : 'Sold out' }}</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('cart.items.store') }}" class="mt-3">
                        @csrf
                        <input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}" />
                        <input type="hidden" name="quantity" value="1" />
                        <input type="hidden" name="checkout" value="1" />
                        <button data-pdp-instant-buy @disabled(!$cartEnabled) class="w-full rounded-xl bg-amber-400 px-5 py-4 font-black text-zinc-950 disabled:opacity-40">Buy Now</button>
                    </form>
                    @if($showShippingNotice)<div class="mt-6 rounded-xl border border-zinc-200 bg-white p-3.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                        @if(filled($pdpShipping['shippingBadgeText'] ?? null))<p class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $pdpShipping['shippingBadgeText'] }}</p>@endif
                        @if(filled($pdpShipping['leadTimeText'] ?? null))<p class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400">{{ $pdpShipping['leadTimeText'] }}</p>@endif
                    </div>@endif
                    @php($descriptionHtml = app(\App\Services\ProductDescriptionHtml::class)->clean($product->description))
                    @if(filled($descriptionHtml))<details open class="mt-7 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <summary class="cursor-pointer px-5 py-5 font-mono text-sm font-bold uppercase tracking-wide text-zinc-800 dark:text-zinc-200">Product Description</summary>
                        <div data-product-description class="border-t border-zinc-200 p-5 text-sm text-zinc-700 dark:border-zinc-800 dark:text-zinc-300 [&_p]:mb-3 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_a]:underline [&_img]:max-w-full overflow-x-auto">{!! $descriptionHtml !!}</div>
                    </details>@endif
                    @if($pdpSpecifications->isNotEmpty())<details open class="mt-7 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <summary class="cursor-pointer px-5 py-5 font-mono text-sm font-bold uppercase tracking-wide text-zinc-800 dark:text-zinc-200">Technical Specifications Matrix</summary>
                        <div class="border-t border-zinc-200 p-5 dark:border-zinc-800">
                            <p class="mb-4 font-mono text-xs uppercase tracking-wide text-amber-600 dark:text-amber-300">Specification matrix</p>
                            @foreach($pdpSpecifications as $specification)
                                <div class="border-b border-zinc-200 py-3 text-sm text-zinc-700 last:border-0 dark:border-zinc-800 dark:text-zinc-300">@if(is_array($specification))<span class="font-semibold">{{ $specification['label'] }}:</span> {{ $specification['value'] }}@else{{ $specification }}@endif</div>
                            @endforeach
                        </div>
                    </details>@endif
                    @if($showShippingPolicy)<details class="mt-3 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <summary class="flex cursor-pointer items-center justify-between px-5 py-5 text-xs font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">{{ $pdpShipping['policyTitle'] }} <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="space-y-2 border-t border-zinc-200 p-5 pt-4 text-xs text-zinc-700 dark:border-zinc-800/80 dark:text-zinc-300">@if(filled($pdpShipping['policyContentHtml'] ?? null))<p>{{ $pdpShipping['policyContentHtml'] }}</p>@endif</div>
                    </details>@endif
                    @foreach($pdpCustomAccordions as $accordion)
                        <details @if(!empty($accordion['defaultOpen'])) open @endif class="mt-3 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                            <summary class="flex cursor-pointer items-center justify-between px-5 py-5 text-xs font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">{{ $accordion['title'] }} <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                            <div class="space-y-2 border-t border-zinc-200 p-5 pt-4 text-xs text-zinc-700 dark:border-zinc-800/80 dark:text-zinc-300">{{ $accordion['content'] }}</div>
                        </details>
                    @endforeach
                    @if($pdpReviews->isNotEmpty())<details class="mt-3 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                    <summary class="flex cursor-pointer items-center justify-between px-5 py-5 text-xs font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Verified Studio Reviews ({{ $pdpReviews->count() }}) <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="space-y-3 border-t border-zinc-200 p-5 pt-4 text-xs text-zinc-700 dark:border-zinc-800/80 dark:text-zinc-300">@foreach($pdpReviews as $review)<div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs dark:border-zinc-800/80 dark:bg-zinc-950"><div class="flex items-center justify-between"><span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $review->customer_name_snapshot ?: 'Verified Studio Buyer' }}</span><span class="font-mono text-[10px] text-zinc-500">{{ $review->published_at?->format('M j, Y') }}</span></div><div class="mt-1 flex items-center gap-1 text-amber-500 dark:text-amber-400">@for($star = 0; $star < $review->rating; $star++)<i data-lucide="star" class="h-3 w-3 fill-current"></i>@endfor<span class="ml-1 text-[10px] text-emerald-600 dark:text-emerald-400">Verified Studio Buyer</span></div><p class="mt-1 text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $review->title }}</p><p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $review->comment }}</p></div>@endforeach</div>
                    </details>@endif
                </section>
            </section>

            @if($companions->isNotEmpty())<section class="mt-20 border-t border-zinc-200 pt-12 dark:border-zinc-900">
                <p class="font-mono text-sm font-bold uppercase tracking-[0.16em] text-amber-600 dark:text-amber-300">Modular Synergy</p>
                <h2 class="mt-2 font-serif text-4xl font-black text-zinc-900 dark:text-white">Frequently Paired With This Model</h2>
                <div class="mt-7 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($companions as $companion)<x-storefront.product-card-grid :product="$companion" />@endforeach
                </div>
                @foreach($companions as $companion)@if($companion->variants->isNotEmpty())<x-storefront.quick-add-variant-modal :product="$companion" :variants="$companion->variants" :images="$companion->images" />@endif @endforeach
            </section>@endif
        </main>
        <x-storefront.footer />
    </div>
    <div data-pdp-sticky-purchase class="fixed bottom-0 left-0 right-0 z-30 border-t border-zinc-200 bg-white/95 px-4 py-3 shadow-2xl backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/95 sm:px-6">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3"><img data-pdp-sticky-image src="{{ $selectedImage?->path ?: $fallbackImage }}" alt="" class="hidden h-10 w-10 shrink-0 rounded-lg object-cover sm:block"><div class="min-w-0"><p class="truncate text-xs font-bold text-zinc-900 dark:text-white">{{ $product->title }}</p><p class="truncate text-[11px] text-zinc-500 dark:text-zinc-400">Selected: <strong data-pdp-sticky-label class="text-amber-600 dark:text-amber-300">{{ $selectedVariant?->color_name ?: $selectedVariant?->title }}</strong> • SKU: <span data-pdp-sticky-sku>{{ $selectedVariant?->sku }}</span></p></div></div>
            <div class="flex shrink-0 items-center gap-3"><div class="text-right"><p data-pdp-sticky-price class="text-sm font-bold text-zinc-900 dark:text-white">@storefrontMoney($price)</p></div><form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}"><input type="hidden" name="quantity" value="1"><button data-pdp-sticky-add @disabled(!$cartEnabled) class="flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-amber-400 hover:text-zinc-950 disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400 sm:px-6 sm:text-sm"><i data-lucide="shopping-bag" class="h-4 w-4"></i>Add to Cart</button></form></div>
        </div>
    </div>
</x-layouts.app>
