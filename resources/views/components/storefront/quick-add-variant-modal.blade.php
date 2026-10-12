@props(['product', 'variants', 'images', 'theme' => 'universal'])

@php
    $payload = app(\App\Services\StorefrontVariantData::class)->payload($variants, $images);

    $configuredOptions = collect($product->options ?? [])
        ->filter(fn ($option) => is_array($option) && filled($option['name'] ?? null) && is_array($option['values'] ?? null) && count($option['values']))
        ->values();

    if ($configuredOptions->isEmpty()) {
        $configuredOptions = collect([[
            'id' => 'legacy-finish',
            'name' => 'Finish',
            'displayType' => 'pills',
            'values' => $payload->pluck('title')->filter()->values()->all(),
            'valueVisuals' => $payload->mapWithKeys(fn ($variant) => [$variant['title'] => [
                'visualType' => $variant['visualType'] ?: ($variant['optionImage'] ? 'image' : ($variant['colorCode'] ? 'color' : 'none')),
                'colorCode' => $variant['colorCode'],
                'optionImage' => $variant['optionImage'] ?: $variant['image'],
            ]])->all(),
        ]]);
    }

    $selectorOptions = $configuredOptions->map(fn ($option) => [
        'id' => (string) ($option['id'] ?? $option['name']),
        'name' => (string) $option['name'],
        'displayType' => in_array($option['displayType'] ?? null, ['color_palette', 'option_image', 'pills', 'dropdown'], true) ? $option['displayType'] : 'pills',
        'values' => array_values(array_map('strval', $option['values'])),
        'valueVisuals' => is_array($option['valueVisuals'] ?? null) ? $option['valueVisuals'] : [],
    ])->values();

    $payload = $payload->map(function (array $variant) use ($selectorOptions) {
        if (filled($variant['options'])) return $variant;
        $titleValues = collect(explode(' / ', (string) $variant['title']))->map(fn ($value) => trim($value))->values();
        if ($titleValues->count() !== $selectorOptions->count()) return $variant;
        $normalized = [];
        foreach ($selectorOptions as $index => $option) {
            $value = $titleValues->get($index);
            if (! in_array($value, $option['values'], true)) return $variant;
            $normalized[$option['name']] = $value;
        }
        $variant['options'] = $normalized;
        return $variant;
    });

    $initial = $payload->first();
    $initialImage = $initial['optionImage'] ?: $initial['image'] ?: $images->first()?->path ?: asset('images/image-not-found.png');
    $initialSavings = max(0, (int) ($initial['compareAt'] ?? 0) - (int) ($initial['price'] ?? 0));
@endphp

<div
    data-quick-add-modal
    data-quick-add-product-id="{{ $product->id }}"
    data-quick-add-variants='@json($payload)'
    data-quick-add-options='@json($selectorOptions)'
    data-quick-add-product-url="{{ route('storefront.products.show', ['handle' => $product->handle]) }}"
    class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-black/75 p-3 backdrop-blur-md animate-in fade-in duration-200 sm:p-4 md:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="quick-add-title-{{ $product->id }}"
    aria-hidden="true"
>
    <div data-quick-add-backdrop class="absolute inset-0"></div>
    <div class="relative my-auto flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-2xl transition-all dark:border-zinc-800 dark:bg-zinc-950 md:flex-row">
        <button data-quick-add-close type="button" aria-label="Close modal" class="absolute right-4 top-4 z-20 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-zinc-200/60 bg-zinc-100/90 text-zinc-600 shadow-sm transition-colors hover:bg-zinc-200 dark:border-zinc-800 dark:bg-zinc-900/90 dark:text-zinc-300 dark:hover:bg-zinc-800">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>

        <div class="flex w-full shrink-0 flex-col justify-between border-b border-zinc-200 bg-zinc-100 p-5 dark:border-zinc-800 dark:bg-zinc-900/90 sm:p-6 md:w-5/12 md:border-b-0 md:border-r">
            <div class="mb-3 flex items-center justify-between gap-2">
                <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 font-mono text-[11px] font-bold uppercase tracking-widest text-amber-600 dark:bg-amber-400/10 dark:text-amber-400">{{ $product->brand }}</span>
                <span data-quick-add-saving class="{{ $initialSavings > 0 ? '' : 'hidden' }} rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 font-mono text-[11px] font-bold uppercase text-emerald-600 dark:text-emerald-400">Save @storefrontMoney($initialSavings) ({{ ($initial['compareAt'] ?? 0) > 0 ? (int) round(($initialSavings / $initial['compareAt']) * 100) : 0 }}% OFF)</span>
            </div>

            <div class="group relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-inner dark:border-zinc-800/80 dark:bg-zinc-950">
                <img data-quick-add-hero src="{{ $initialImage }}" alt="{{ $product->title }}" referrerpolicy="no-referrer" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                <div data-quick-add-sold-out-overlay class="{{ (($initial['stock'] ?? 0) > 0 && ($initial['enabled'] ?? true)) ? 'hidden' : '' }} absolute inset-0 flex items-center justify-center bg-black/60 p-4 backdrop-blur-[2px]">
                    <div class="rounded-xl border border-rose-500/40 bg-rose-950/90 px-4 py-2 text-center text-white shadow-lg"><span class="block font-mono text-xs font-bold uppercase tracking-wider text-rose-300">{{ $theme === 'home_deco' ? 'Availability' : 'Currently Sold Out' }}</span><span class="mt-0.5 block text-[10px] text-zinc-300">{{ $theme === 'home_deco' ? 'See availability for your selected option.' : 'Select another finish or join restock notification' }}</span></div>
                </div>
                <div class="absolute bottom-2.5 left-2.5 flex items-center gap-1.5 rounded-lg border border-white/10 bg-black/60 px-2.5 py-1 font-mono text-[10px] text-zinc-300 shadow-sm backdrop-blur-md"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span><span>SKU: <span data-quick-add-sku>{{ $initial['sku'] }}</span></span></div>
            </div>

            @if($images->count() > 1)
                <div class="scrollbar-none mt-4 flex items-center gap-2 overflow-x-auto pb-1">
                    @foreach($images as $image)
                        <button type="button" data-quick-add-thumbnail data-quick-add-image="{{ $image->path }}" class="relative h-12 w-12 shrink-0 cursor-pointer overflow-hidden rounded-xl border-2 transition-all {{ $image->path === $initialImage ? 'scale-105 border-amber-500 ring-2 ring-amber-500/30 shadow-md' : 'border-zinc-200 opacity-70 hover:border-zinc-400 hover:opacity-100 dark:border-zinc-800' }}"><img src="{{ $image->path }}" alt="Angle preview" referrerpolicy="no-referrer" class="h-full w-full object-cover"></button>
                    @endforeach
                </div>
            @endif

        </div>

        <div class="flex max-h-[75vh] w-full flex-col justify-between space-y-5 overflow-y-auto p-5 sm:p-7 md:max-h-[90vh] md:w-7/12">
            <div>
                <h2 id="quick-add-title-{{ $product->id }}" class="font-serif text-xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-2xl">{{ $product->title }}</h2>
                <p class="mt-1 line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400">{{ app(\App\Services\ProductDescriptionHtml::class)->excerpt($product->description, $product->subtitle) }}</p>
                <div class="mt-3 flex items-baseline gap-3"><span data-quick-add-price class="font-sans text-2xl font-bold text-zinc-900 dark:text-zinc-100">@storefrontMoney($initial['price'] ?? 0)</span><span data-quick-add-compare class="{{ filled($initial['compareAt']) ? '' : 'hidden' }} text-sm text-zinc-400 line-through dark:text-zinc-500">{{ filled($initial['compareAt']) ? app(\App\Support\StorefrontCurrencyContext::class)->format($initial['compareAt']) : '' }}</span><span data-quick-add-save class="{{ $initialSavings > 0 ? '' : 'hidden' }} rounded-md bg-amber-500/15 px-2 py-0.5 font-mono text-xs font-semibold text-amber-700 dark:text-amber-300">SAVE @storefrontMoney($initialSavings)</span></div>
            </div>

            <div data-quick-add-selector class="space-y-4 border-y border-zinc-200 py-3 dark:border-zinc-800">
                <div class="flex items-center justify-between pt-1 text-xs"><div class="flex items-center gap-1.5"><span class="font-mono text-[11px] font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">Finish / Option:</span><strong data-quick-add-selected-label class="font-sans text-sm font-bold text-amber-600 dark:text-amber-400">{{ $initial['title'] }}</strong></div><div data-quick-add-availability class="flex items-center gap-1.5 font-mono text-[11px] font-medium"><span data-quick-add-availability-dot class="h-2 w-2 rounded-full bg-emerald-400"></span><span data-quick-add-availability-text></span></div></div>

                @foreach($selectorOptions as $option)
                    @php
                        $visuals = $option['valueVisuals'];
                    @endphp
                    <section data-quick-add-option-selector data-quick-add-option-name="{{ $option['name'] }}" class="space-y-3 pt-1">
                        <div class="flex items-center justify-between text-xs"><span class="font-mono text-[11px] font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">{{ $option['name'] }}</span><span class="font-mono text-[10px] text-zinc-500 dark:text-zinc-400">{{ count($option['values']) }} options</span></div>
                        @if($option['displayType'] === 'dropdown')
                            <div class="relative"><select data-quick-add-option-dropdown data-quick-add-option-name="{{ $option['name'] }}" aria-label="Select {{ $option['name'] }}" class="w-full cursor-pointer appearance-none rounded-xl border border-zinc-200 bg-white px-3.5 py-2.5 text-xs font-medium text-zinc-900 shadow-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/40 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100">@foreach($option['values'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select><i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"></i></div>
                        @else
                            <div role="radiogroup" aria-label="{{ $option['name'] }} options" class="flex flex-wrap items-center gap-2.5 pt-1">
                                @foreach($option['values'] as $value)
                                    @php
                                        $visual = is_array($visuals[$value] ?? null) ? $visuals[$value] : [];
                                        $mode = ($visual['visualType'] ?? 'none') === 'color' && filled($visual['colorCode'] ?? null) ? 'color' : ((($visual['visualType'] ?? 'none') === 'image' && filled($visual['optionImage'] ?? null)) ? 'image' : 'pill');
                                    @endphp
                                    @if($mode === 'color')
                                        <button type="button" role="radio" data-quick-add-option-value="{{ $value }}" data-quick-add-option-name="{{ $option['name'] }}" data-quick-add-option-visual="color" aria-label="{{ $value }}" class="group relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border-2 border-zinc-300 shadow-sm transition-all hover:scale-105 hover:border-zinc-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-zinc-700" style="background-color:{{ $visual['colorCode'] }}"><i data-quick-add-option-check data-lucide="check" class="hidden h-4 w-4 text-white drop-shadow"></i><span data-quick-add-option-sold-out class="absolute inset-0 m-auto hidden h-[1.5px] w-full rotate-45 bg-rose-500"></span></button>
                                    @elseif($mode === 'image')
                                        <button type="button" role="radio" data-quick-add-option-value="{{ $value }}" data-quick-add-option-name="{{ $option['name'] }}" data-quick-add-option-visual="image" aria-label="{{ $value }}" class="group relative h-16 w-16 cursor-pointer overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 transition-all hover:border-zinc-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-900"><img src="{{ $visual['optionImage'] }}" alt="{{ $value }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"><span data-quick-add-option-check class="absolute right-1.5 top-1.5 hidden h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-zinc-950 shadow-lg ring-1 ring-black/20"><i data-lucide="check" class="h-3 w-3 stroke-[3]"></i></span><span data-quick-add-option-sold-out class="absolute inset-0 hidden items-center justify-center bg-black/60 backdrop-blur-[1px]"><span class="rounded bg-rose-600/90 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-white">Sold Out</span></span></button>
                                    @else
                                        <button type="button" role="radio" data-quick-add-option-value="{{ $value }}" data-quick-add-option-name="{{ $option['name'] }}" data-quick-add-option-visual="pill" aria-label="{{ $value }}" class="flex cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3.5 py-2 text-xs font-medium text-zinc-700 transition-all hover:border-zinc-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300"><span>{{ $value }}</span><i data-quick-add-option-check data-lucide="check" class="hidden h-3.5 w-3.5 shrink-0 text-amber-600 dark:text-amber-400"></i></button>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="space-y-3 pt-2">
                <form data-quick-add-form method="POST" action="{{ route('cart.items.store') }}">
                    @csrf
                    <input data-quick-add-variant-input type="hidden" name="variant_id" value="{{ $initial['id'] }}">
                    <input data-quick-add-quantity-input type="hidden" name="quantity" value="1">
                    <div class="flex items-center gap-3">
                        <div class="flex shrink-0 items-center rounded-xl border border-zinc-300 bg-zinc-50 p-1 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"><button data-quick-add-decrease type="button" aria-label="Decrease quantity" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-200 disabled:cursor-not-allowed disabled:opacity-30 dark:text-zinc-300 dark:hover:bg-zinc-800"><i data-lucide="minus" class="h-3.5 w-3.5"></i></button><span data-quick-add-quantity class="w-10 text-center font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100">1</span><button data-quick-add-increase type="button" aria-label="Increase quantity" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-200 disabled:cursor-not-allowed disabled:opacity-30 dark:text-zinc-300 dark:hover:bg-zinc-800"><i data-lucide="plus" class="h-3.5 w-3.5"></i></button></div>
                        <button data-quick-add-confirm type="submit" class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl bg-zinc-900 px-6 py-3.5 text-sm font-bold text-white shadow-lg transition-all hover:bg-amber-500 hover:text-zinc-950 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-amber-400"><span data-quick-add-confirm-default class="flex items-center gap-2"><i data-lucide="shopping-bag" class="h-4 w-4 shrink-0"></i><span data-quick-add-confirm-text>Add to Cart</span></span><span data-quick-add-confirm-loading class="hidden items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span><span>Updating Cart...</span></span><span data-quick-add-confirm-success class="hidden items-center gap-2"><i data-lucide="circle-check" class="h-4 w-4 animate-bounce text-white"></i><span>Added to Cart Successfully!</span></span><span data-quick-add-confirm-sold-out class="hidden items-center gap-2"><i data-lucide="circle-alert" class="h-4 w-4 text-rose-500"></i><span>Sold Out</span></span></button>
                    </div>
                </form>
                <p data-quick-add-error role="alert" class="hidden rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-medium text-rose-600 dark:text-rose-300"></p>
                <div class="flex items-center justify-between pt-1"><a href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}" data-quick-add-product-link class="group flex items-center gap-1.5 font-mono text-xs font-semibold text-zinc-600 transition-colors hover:text-amber-600 dark:text-zinc-400 dark:hover:text-amber-400"><span>{{ $theme === 'home_deco' ? 'Product details' : 'View Full Product Specifications & Lab Tests' }}</span><i data-lucide="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"></i></a><span data-quick-add-stock-note class="font-mono text-[11px] text-zinc-400 dark:text-zinc-500"></span></div>
            </div>
        </div>
    </div>
</div>
