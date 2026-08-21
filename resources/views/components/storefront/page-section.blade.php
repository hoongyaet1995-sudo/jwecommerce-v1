@props(['section', 'products'])
@php
    $content = $section->content ?? [];
    $type = $section->type;
    $title = data_get($content, 'headline', data_get($content, 'title', ''));
    $eyebrow = data_get($content, 'eyebrow', data_get($content, 'subtitle', ''));
    $body = data_get($content, 'body', data_get($content, 'content', ''));
    $items = data_get($content, 'items', []);
    $ctaLabel = data_get($content, 'cta_label', data_get($content, 'buttonText'));
    $ctaUrl = data_get($content, 'cta_url', data_get($content, 'buttonLink', '#'));
@endphp

@if(data_get($content, 'isVisible', true) && in_array($type, ['hero', 'hero_banner']))
    <section class="relative overflow-hidden border-b border-white/10 bg-gradient-to-br from-zinc-950 via-zinc-900 to-indigo-950 px-5 py-24 sm:py-32">
        <div class="mx-auto max-w-5xl text-center">
            @if($eyebrow)<p class="font-mono text-xs font-bold uppercase tracking-widest text-amber-300">{{ $eyebrow }}</p>@endif
            <h1 class="mt-4 font-serif text-4xl font-black tracking-tight text-white sm:text-6xl">{{ $title }}</h1>
            @if($body)<p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-zinc-300">{{ $body }}</p>@endif
            @if($ctaLabel)<a href="{{ $ctaUrl }}" class="mt-8 inline-flex rounded-xl bg-white px-6 py-3 text-sm font-bold text-zinc-950">{{ $ctaLabel }} →</a>@endif
        </div>
    </section>
@elseif(data_get($content, 'isVisible', true) && $type === 'product_grid')
    @php($productIds = data_get($content, 'product_ids', data_get($content, 'productIds')))
    @php($selected = $productIds ? $products->whereIn('id', $productIds) : $products->take(data_get($content, 'columns', data_get($content, 'columnsCount', 3))))
    <section class="mx-auto max-w-7xl px-5 py-16">
        <div class="mb-8">@if($eyebrow)<p class="font-mono text-xs font-bold uppercase tracking-widest text-amber-300">{{ $eyebrow }}</p>@endif<h2 class="mt-3 font-serif text-3xl font-black text-white">{{ $title }}</h2>@if($body)<p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-400">{{ $body }}</p>@endif</div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($selected as $product)
                <a href="{{ route('storefront.products.show', $product) }}" class="rounded-2xl border border-white/10 bg-zinc-900 p-4 transition hover:border-amber-300/60">
                    @if($product->images->first())<img src="{{ $product->images->first()->path }}" alt="{{ $product->title }}" class="aspect-square w-full rounded-xl object-cover">@endif
                    <h3 class="mt-4 text-sm font-bold text-white">{{ $product->title }}</h3><p class="mt-2 font-mono text-sm text-amber-300">${{ number_format(($product->variants->first()?->price ?? $product->base_price) / 100, 2) }}</p>
                </a>
            @endforeach
        </div>
    </section>
@elseif(data_get($content, 'isVisible', true) && in_array($type, ['feature_cards', 'media_gallery']))
    <section class="mx-auto max-w-7xl px-5 py-16"><div class="mb-8">@if($eyebrow)<p class="font-mono text-xs font-bold uppercase tracking-widest text-amber-300">{{ $eyebrow }}</p>@endif<h2 class="mt-3 font-serif text-3xl font-black text-white">{{ $title }}</h2></div><div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($items as $item)<article class="overflow-hidden rounded-2xl border border-white/10 bg-zinc-900 p-5">@if(data_get($item, 'image_url', data_get($item, 'imageUrl')))<img src="{{ data_get($item, 'image_url', data_get($item, 'imageUrl')) }}" alt="{{ data_get($item, 'title') }}" class="mb-5 aspect-[4/3] w-full rounded-xl object-cover">@endif<h3 class="text-base font-bold text-white">{{ data_get($item, 'title') }}</h3><p class="mt-2 text-sm leading-6 text-zinc-400">{{ data_get($item, 'description') }}</p></article>@endforeach</div></section>
@elseif(data_get($content, 'isVisible', true) && $type === 'faq_accordion')
    <section class="mx-auto max-w-4xl px-5 py-16">@if($eyebrow)<p class="font-mono text-xs font-bold uppercase tracking-widest text-amber-300">{{ $eyebrow }}</p>@endif<h2 class="mt-3 font-serif text-3xl font-black text-white">{{ $title }}</h2><div class="mt-8 space-y-3">@foreach($items as $item)<details class="rounded-xl border border-white/10 bg-zinc-900 p-5"><summary class="cursor-pointer text-sm font-bold text-white">{{ data_get($item, 'title') }}</summary><p class="mt-4 text-sm leading-6 text-zinc-400">{{ data_get($item, 'description') }}</p></details>@endforeach</div></section>
@elseif(data_get($content, 'isVisible', true))
    <section class="mx-auto max-w-4xl px-5 py-16 text-center">@if($eyebrow)<p class="font-mono text-xs font-bold uppercase tracking-widest text-amber-300">{{ $eyebrow }}</p>@endif<h2 class="mt-3 font-serif text-3xl font-black text-white">{{ $title }}</h2>@if($body)<p class="mx-auto mt-5 max-w-2xl whitespace-pre-line text-base leading-8 text-zinc-300">{{ $body }}</p>@endif
        @if($ctaLabel)<a href="{{ $ctaUrl }}" class="mt-7 inline-flex rounded-xl bg-white px-5 py-3 text-sm font-bold text-zinc-950">{{ $ctaLabel }} →</a>@endif</section>
@endif
