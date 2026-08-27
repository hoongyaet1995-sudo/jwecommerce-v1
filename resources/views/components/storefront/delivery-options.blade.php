@props(['summary', 'options', 'selected' => null, 'quoteUrl' => null])

@php
    $selected = old('delivery', $selected ?? array_key_first($options) ?? '');
@endphp

<section class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70" data-delivery-matrix @if($quoteUrl) data-delivery-quote-url="{{ $quoteUrl }}" @endif data-subtotal="{{ $summary['subtotal'] }}" data-discount="{{ $summary['discount'] }}" data-tax="{{ $summary['tax'] }}" data-tax-quote='@json($summary['tax_quote'] ?? [])'>
    <div class="flex items-center gap-3 border-b border-zinc-200 pb-3 dark:border-zinc-800"><span class="grid h-7 w-7 place-items-center rounded-full bg-amber-400 text-xs font-black text-zinc-950">2</span><h2 class="font-serif text-sm font-bold text-zinc-900 dark:text-zinc-100">Delivery Method &amp; Logistics Matrix</h2></div>
    <div class="space-y-2.5">
        @foreach($options as $value => $option)
            <label data-delivery-option data-delivery-shipping="{{ $option['shipping'] }}" @class(['flex cursor-pointer items-center justify-between rounded-xl border p-4 transition', 'border-amber-400 bg-amber-400/10 ring-1 ring-amber-400/30' => $selected === $value, 'border-zinc-200 bg-zinc-50 hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-950/60 dark:hover:border-zinc-700' => $selected !== $value])>
                <span><input type="radio" name="delivery" value="{{ $value }}" class="mr-3 accent-amber-400" @checked($selected === $value) /><span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $option['label'] }}</span><span class="mt-1 block pl-6 text-xs text-zinc-500 dark:text-zinc-400">{{ $option['detail'] }}</span></span>
                <strong class="font-mono text-xs {{ $option['shipping'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-100' }}">{{ $option['shipping'] === 0 ? 'FREE' : app(\App\Support\StorefrontCurrencyContext::class)->format($option['shipping']) }}</strong>
            </label>
        @endforeach
    </div>
    <p class="text-xs text-zinc-500 dark:text-zinc-400">Delivery and tax are recalculated server-side when the order is authorized.</p>
</section>
