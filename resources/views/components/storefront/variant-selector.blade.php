@props(['product', 'variants', 'images', 'selectedVariant'])

@php
    $selectedAvailable = $selectedVariant ? app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($selectedVariant) : null;
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

    $selectorOptions = $configuredOptions->map(function ($option) {
        return [
            'id' => (string) ($option['id'] ?? $option['name']),
            'name' => (string) $option['name'],
            'displayType' => in_array($option['displayType'] ?? null, ['color_palette', 'option_image', 'pills', 'dropdown'], true) ? $option['displayType'] : 'pills',
            'values' => array_values(array_map('strval', $option['values'])),
            'valueVisuals' => is_array($option['valueVisuals'] ?? null) ? $option['valueVisuals'] : [],
        ];
    })->values();

    // Legacy products created before per-variant option maps stored their exact
    // combination only in the title ("Color / Material"). Normalize only an
    // unambiguous, ordered title that matches the persisted option values.
    $selectedAvailable = $selectedVariant ? app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($selectedVariant) : null;
    $payload = $payload->map(function (array $variant) use ($selectorOptions) {
        if (filled($variant['options'])) {
            return $variant;
        }

        $titleValues = collect(explode(' / ', (string) $variant['title']))
            ->map(fn ($value) => trim($value))
            ->values();

        if ($titleValues->count() !== $selectorOptions->count()) {
            return $variant;
        }

        $normalized = [];
        foreach ($selectorOptions as $index => $option) {
            $value = $titleValues->get($index);
            if (!in_array($value, $option['values'], true)) {
                return $variant;
            }

            $normalized[$option['name']] = $value;
        }

        $variant['options'] = $normalized;

        return $variant;
    });

    $selectedPayload = $payload->firstWhere('id', (string) $selectedVariant?->id);
    $selectedValues = is_array($selectedPayload['options'] ?? null) ? $selectedPayload['options'] : [];
    foreach ($selectorOptions as $option) {
        if (!filled($selectedValues[$option['name']] ?? null)) {
            $selectedValues[$option['name']] = $option['values'][0] ?? '';
        }
    }
@endphp

<div
    data-storefront-variant-selector
    data-variants='@json($payload)'
    data-options='@json($selectorOptions)'
    data-selected-variant-id="{{ $selectedVariant?->id }}"
    class="space-y-5 font-sans"
>
    <div class="flex items-center justify-between text-xs">
        <div class="flex items-center gap-1.5">
            <span class="font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Finish / Option:</span>
            <strong data-pdp-selected-label class="text-amber-600 dark:text-amber-400 font-sans font-bold">{{ $selectedVariant?->title }}</strong>
        </div>
        <div data-pdp-availability class="flex items-center gap-1.5 font-mono text-[11px] font-medium {{ ($selectedAvailable ?? 0) < 1 ? 'text-rose-500' : (($selectedAvailable ?? 0) <= 5 ? 'text-amber-500' : 'text-emerald-600') }}">
            <span data-pdp-availability-dot class="w-2 h-2 rounded-full {{ ($selectedAvailable ?? 0) < 1 ? 'bg-rose-500' : (($selectedAvailable ?? 0) <= 5 ? 'bg-amber-400 animate-pulse' : 'bg-emerald-400 animate-pulse') }}"></span>
            <span data-pdp-availability-text>{{ ($selectedAvailable ?? 0) < 1 ? ($selectedAvailable === null ? 'Awaiting stock verification' : 'Out of Stock') : (($selectedAvailable ?? 0) <= 5 ? 'Low Stock (Only '.($selectedAvailable ?? 0).' remaining)' : 'In Stock ('.($selectedAvailable ?? 0).' units)') }}</span>
        </div>
    </div>

    @foreach($selectorOptions as $option)
        @php
            $optionName = $option['name'];
            $selectedValue = (string) ($selectedValues[$optionName] ?? '');
            $visuals = $option['valueVisuals'];
            $hasVisual = collect($option['values'])->contains(function ($value) use ($visuals) {
                $visual = is_array($visuals[$value] ?? null) ? $visuals[$value] : [];
                return (($visual['visualType'] ?? 'none') === 'color' && filled($visual['colorCode'] ?? null))
                    || (($visual['visualType'] ?? 'none') === 'image' && filled($visual['optionImage'] ?? null));
            });
            $asDropdown = $option['displayType'] === 'dropdown' && !$hasVisual;
        @endphp

        <section data-pdp-option-selector data-pdp-option-name="{{ $optionName }}" data-pdp-option-display-mode="{{ $asDropdown ? 'dropdown' : $option['displayType'] }}" class="space-y-3">
            <div class="flex items-center justify-between text-xs pt-1">
                <span class="font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider text-[11px] font-mono">{{ $optionName }}</span>
                <span class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400">{{ count($option['values']) }} options</span>
            </div>

            @if($asDropdown)
                <div class="relative">
                    <select data-pdp-option-dropdown data-pdp-option-name="{{ $optionName }}" aria-label="Select {{ $optionName }}" class="w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl px-3.5 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 font-medium appearance-none outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 cursor-pointer shadow-sm">
                        @foreach($option['values'] as $value)
                            @php
                                $valueVariants = $payload->filter(fn ($variant) => ($variant['options'][$optionName] ?? $variant['title']) === $value);
                                $outOfStock = $valueVariants->isNotEmpty() && $valueVariants->every(fn ($variant) => $variant['stock'] < 1 || !$variant['enabled']);
                            @endphp
                            <option value="{{ $value }}" @selected($selectedValue === $value) @disabled($valueVariants->isEmpty() || $valueVariants->every(fn ($variant) => !$variant['enabled']))>{{ $value }}{{ $outOfStock ? ' (Out of Stock)' : '' }}</option>
                        @endforeach
                    </select>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
            @else
                <div role="radiogroup" aria-label="{{ $optionName }} options" class="flex flex-wrap items-center gap-2.5 pt-1">
                    @foreach($option['values'] as $value)
                        @php
                            $visual = is_array($visuals[$value] ?? null) ? $visuals[$value] : [];
                            $mode = ($visual['visualType'] ?? 'none') === 'color' && filled($visual['colorCode'] ?? null)
                                ? 'color'
                                : (($visual['visualType'] ?? 'none') === 'image' && filled($visual['optionImage'] ?? null) ? 'image' : 'pill');
                            $valueVariants = $payload->filter(fn ($variant) => ($variant['options'][$optionName] ?? $variant['title']) === $value);
                            $isSelected = $selectedValue === $value;
                            $isUnavailable = $valueVariants->isEmpty() || $valueVariants->every(fn ($variant) => !$variant['enabled']);
                            $isOutOfStock = $valueVariants->isNotEmpty() && $valueVariants->every(fn ($variant) => $variant['stock'] < 1 || !$variant['enabled']);
                            $price = $valueVariants->first()['price'] ?? null;
                        @endphp
                        @if($mode === 'color')
                            <button type="button" role="radio" aria-checked="{{ $isSelected ? 'true' : 'false' }}" aria-disabled="{{ ($isUnavailable || $isOutOfStock) ? 'true' : 'false' }}" aria-label="{{ $value }}{{ $isOutOfStock ? ' - Out of Stock' : '' }}" @disabled($isUnavailable) data-pdp-option-value="{{ $value }}" data-pdp-option-name="{{ $optionName }}" data-pdp-option-visual="color" data-pdp-option-stock="{{ $isOutOfStock ? 0 : 1 }}" class="group relative w-10 h-10 rounded-full flex items-center justify-center transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-amber-500 focus:outline-none {{ $isSelected ? 'ring-2 ring-amber-500 dark:ring-amber-400 ring-offset-2 ring-offset-zinc-50 dark:ring-offset-zinc-950 border-2 border-zinc-900 dark:border-white shadow-lg scale-105' : 'border-2 border-zinc-300 dark:border-zinc-700 hover:scale-105 hover:border-zinc-500 dark:hover:border-zinc-400 shadow-sm' }} {{ $isOutOfStock ? 'opacity-40 cursor-not-allowed' : '' }} {{ $isUnavailable ? 'opacity-25 cursor-not-allowed' : '' }}" style="background-color:{{ $visual['colorCode'] }}">
                                <i data-pdp-option-check data-lucide="check" class="w-4 h-4 {{ $isSelected ? '' : 'hidden' }} {{ in_array(strtolower($visual['colorCode']), ['#ffffff', '#fff', '#f8fafc', '#e2e8f0']) ? 'text-zinc-950' : 'text-white' }} drop-shadow"></i>
                                @if($isOutOfStock)<span class="absolute inset-0 m-auto w-full h-[1.5px] bg-rose-500 rotate-45 pointer-events-none"></span>@endif
                            </button>
                        @elseif($mode === 'image')
                            <button type="button" role="radio" aria-checked="{{ $isSelected ? 'true' : 'false' }}" aria-disabled="{{ ($isUnavailable || $isOutOfStock) ? 'true' : 'false' }}" aria-label="{{ $value }}{{ $isOutOfStock ? ' - Out of Stock' : '' }}" @disabled($isUnavailable) data-pdp-option-value="{{ $value }}" data-pdp-option-name="{{ $optionName }}" data-pdp-option-visual="image" data-pdp-option-stock="{{ $isOutOfStock ? 0 : 1 }}" class="group relative w-16 h-16 rounded-xl overflow-hidden bg-zinc-100 dark:bg-zinc-900 border transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-amber-500 focus:outline-none {{ $isSelected ? 'border-amber-500 dark:border-amber-400 ring-2 ring-amber-500/40 dark:ring-amber-400/40 ring-offset-2 ring-offset-zinc-50 dark:ring-offset-zinc-950 shadow-md' : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600' }} {{ $isOutOfStock ? 'opacity-40 grayscale cursor-not-allowed' : '' }} {{ $isUnavailable ? 'opacity-25 cursor-not-allowed' : '' }}">
                                <img src="{{ $visual['optionImage'] }}" alt="{{ $value }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <span data-pdp-option-check class="{{ $isSelected ? '' : 'hidden' }} absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-amber-500 text-zinc-950 flex items-center justify-center shadow-lg ring-1 ring-black/20"><i data-lucide="check" class="w-3 h-3 stroke-[3]"></i></span>
                                @if($isOutOfStock)<span class="absolute inset-0 bg-black/60 backdrop-blur-[1px] flex items-center justify-center"><span class="text-[9px] font-bold uppercase tracking-wider text-white bg-rose-600/90 px-1.5 py-0.5 rounded">Sold Out</span></span>@endif
                            </button>
                        @else
                            <button type="button" role="radio" aria-checked="{{ $isSelected ? 'true' : 'false' }}" aria-disabled="{{ ($isUnavailable || $isOutOfStock) ? 'true' : 'false' }}" aria-label="{{ $value }}{{ $isOutOfStock ? ' - Out of Stock' : '' }}" @disabled($isUnavailable) data-pdp-option-value="{{ $value }}" data-pdp-option-name="{{ $optionName }}" data-pdp-option-visual="pill" data-pdp-option-stock="{{ $isOutOfStock ? 0 : 1 }}" class="px-3.5 py-2 rounded-xl text-xs font-medium border transition-all cursor-pointer flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-amber-500 focus:outline-none {{ $isSelected ? 'border-amber-500 dark:border-amber-400 bg-amber-500/10 dark:bg-amber-400/10 text-zinc-950 dark:text-white ring-1 ring-amber-500/40 shadow-sm font-semibold' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 hover:bg-zinc-50 dark:hover:bg-zinc-900 text-zinc-700 dark:text-zinc-300 hover:border-zinc-300 dark:hover:border-zinc-700' }} {{ $isOutOfStock ? 'border-dashed border-zinc-300 dark:border-zinc-700 text-zinc-400 dark:text-zinc-600 line-through opacity-60 cursor-not-allowed bg-zinc-100/50 dark:bg-zinc-950' : '' }} {{ $isUnavailable ? 'opacity-30 cursor-not-allowed' : '' }}"><span>{{ $value }}</span>@if($price)<span class="font-mono text-[11px] text-zinc-400 dark:text-zinc-500">@storefrontMoney($price)</span>@endif<i data-pdp-option-check data-lucide="check" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0 {{ $isSelected ? '' : 'hidden' }}"></i></button>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <div data-pdp-specs class="pt-1 flex items-center gap-2 text-xs">
        <span class="text-zinc-500 dark:text-zinc-400 font-mono text-[11px] uppercase tracking-wider">Specs:</span>
        <div data-pdp-specs-value class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-xs font-mono text-zinc-800 dark:text-zinc-200">{{ collect($selectedValues)->filter()->implode(' • ') }}</div>
    </div>
</div>
