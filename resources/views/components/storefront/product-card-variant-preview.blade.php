@props(['product', 'variants', 'images'])

@php
    $payload = $variants->values()->map(function ($variant) use ($images) {
        $linkedImage = $images->firstWhere('product_variant_id', $variant->id);
        return [
            'id' => $variant->id,
            'label' => $variant->color_name ?: $variant->title,
            'price' => (int) $variant->price,
            'compareAt' => $variant->compare_at_price ? (int) $variant->compare_at_price : null,
            'stock' => (int) $variant->stock,
            'enabled' => $variant->enabled !== false,
            'visualType' => $variant->visual_type,
            'colorCode' => $variant->color_code ?: $variant->color_hex,
            'optionImage' => $variant->option_image ?: $variant->image_url ?: $linkedImage?->path,
        ];
    });
@endphp

@if($payload->count() > 1)
    <div data-product-card-variant-preview data-card-variants='@json($payload)' class="flex items-center gap-1.5 pt-1" onclick="event.stopPropagation()">
        @foreach($payload->take(4) as $index => $variant)
            @php
                $selected = $index === 0;
                $outOfStock = $variant['stock'] < 1;
                $mode = $variant['visualType'] ?: ($variant['optionImage'] ? 'image' : ($variant['colorCode'] ? 'color' : 'pill'));
            @endphp
            @if($mode === 'color' && $variant['colorCode'])
                <button type="button" data-card-variant-id="{{ $variant['id'] }}" data-card-variant-visual="color" @disabled(!$variant['enabled']) title="{{ $variant['label'] }} {{ $outOfStock ? '(Out of Stock)' : '' }}" aria-label="Select {{ $variant['label'] }}" class="relative w-4 h-4 rounded-full border transition-all cursor-pointer {{ $selected ? 'ring-2 ring-amber-500 dark:ring-amber-400 ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 border-zinc-900 dark:border-white shadow-sm scale-110' : 'border-zinc-300 dark:border-zinc-700 opacity-70 hover:opacity-100 hover:scale-105' }} {{ $outOfStock ? 'opacity-40' : '' }}" style="background-color:{{ $variant['colorCode'] }}">@if($outOfStock && !$selected)<span class="absolute inset-0 m-auto w-full h-[1px] bg-rose-500 rotate-45 pointer-events-none"></span>@endif</button>
            @elseif($mode === 'image' && $variant['optionImage'])
                <button type="button" data-card-variant-id="{{ $variant['id'] }}" data-card-variant-visual="image" @disabled(!$variant['enabled']) title="{{ $variant['label'] }} {{ $outOfStock ? '(Out of Stock)' : '' }}" aria-label="Select {{ $variant['label'] }}" class="relative w-4 h-4 rounded-md overflow-hidden border transition-all cursor-pointer {{ $selected ? 'ring-2 ring-amber-500 dark:ring-amber-400 ring-offset-1 ring-offset-white dark:ring-offset-zinc-900 border-amber-500 shadow-sm scale-110' : 'border-zinc-300 dark:border-zinc-700 opacity-70 hover:opacity-100 hover:scale-105' }} {{ $outOfStock ? 'opacity-40 grayscale' : '' }}"><img src="{{ $variant['optionImage'] }}" alt="{{ $variant['label'] }}" class="w-full h-full object-cover"></button>
            @else
                <button type="button" data-card-variant-id="{{ $variant['id'] }}" data-card-variant-visual="pill" @disabled(!$variant['enabled']) title="{{ $variant['label'] }} {{ $outOfStock ? '(Out of Stock)' : '' }}" aria-label="Select {{ $variant['label'] }}" class="px-1.5 py-0.5 rounded text-[10px] font-mono border transition-all cursor-pointer {{ $selected ? 'border-amber-500 bg-amber-500/15 text-amber-600 dark:text-amber-400 font-bold' : 'border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 hover:border-zinc-400' }}">{{ substr($variant['label'], 0, 3) }}</button>
            @endif
        @endforeach
        @if($payload->count() > 4)<span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono ml-0.5 whitespace-nowrap">+{{ $payload->count() - 4 }}</span>@endif
    </div>
@endif
