@props(['product'])

@php($defaultVariant = $product->variants->first())

<article data-storefront-product-card class="group flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70 dark:hover:border-zinc-700 dark:hover:shadow-xl">
    <a class="relative aspect-square overflow-hidden bg-zinc-100 dark:bg-zinc-950" href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}">
        <img data-card-image class="h-full w-full object-cover transition duration-500 group-hover:scale-105" src="{{ $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->title }}">
        @if($product->isBuyMode() && $product->compare_at_price)<span class="absolute left-3 top-3 rounded-full bg-red-500/90 px-2 py-0.5 text-[10px] font-bold uppercase text-white">SAVE {{ round((1 - $product->base_price / $product->compare_at_price) * 100) }}%</span>@endif
    </a>
    <div class="flex flex-1 flex-col justify-between space-y-3 p-4">
        <div><div class="flex items-center justify-between gap-2"><span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $product->brand }}</span><span class="truncate font-mono text-[10px] text-emerald-600 dark:text-emerald-400">SKU: {{ $defaultVariant?->sku }}</span></div><a href="{{ route('storefront.products.show', ['handle' => $product->handle]) }}"><h2 class="mt-0.5 truncate text-sm font-bold text-zinc-900 transition group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-300">{{ $product->title }}</h2></a><p class="mt-0.5 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $product->subtitle }}</p></div>
        <x-storefront.product-card-variant-preview :product="$product" :variants="$product->variants" :images="$product->images" />
        @if($defaultVariant)<div class="mt-auto flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800"><div><b data-card-price class="text-sm text-zinc-900 dark:text-zinc-100">@productPrice($product, $defaultVariant->price)</b>@if($product->isBuyMode())<del data-card-compare-at class="ml-1.5 text-xs text-zinc-400">@storefrontMoney($defaultVariant->compare_at_price)</del>@endif</div><input data-card-variant-input type="hidden" value="{{ $defaultVariant->id }}">@if($product->isBuyMode())<button type="button" data-quick-add-trigger data-quick-add-product-id="{{ $product->id }}" title="Select Options &amp; Quick Add" aria-label="Select options for {{ $product->title }}" class="rounded-lg bg-zinc-100 p-2 text-zinc-700 transition hover:bg-amber-400 hover:text-zinc-950 dark:bg-zinc-800 dark:text-zinc-200"><i data-lucide="shopping-bag" class="h-3.5 w-3.5"></i></button>@else<a href="{{ route('storefront.products.show',$product->handle) }}">View details</a>@endif</div>@endif
    </div>
</article>
