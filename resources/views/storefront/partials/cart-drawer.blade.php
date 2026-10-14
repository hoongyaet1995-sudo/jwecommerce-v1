@if(app(\App\Services\WebsiteDesignService::class)->surface()==='jewelry')
@include('storefront.themes.jewelry-cart-drawer')
@elseif(app(\App\Services\WebsiteDesignService::class)->surface()==='baby')
@include('storefront.themes.baby-cart-drawer')
@else
@if(app(\App\Services\WebsiteDesignService::class)->surface()==='tech')
@include('storefront.themes.tech-cart-drawer')
@else
@if(app(\App\Services\WebsiteDesignService::class)->surface()==='food')
@include('storefront.themes.food-cart-drawer')
@else
@if(app(\App\Services\WebsiteDesignService::class)->surface()==='beauty')
@include('storefront.themes.beauty-cart-drawer')
@else
@if(app(\App\Services\WebsiteDesignService::class)->surface() === 'home_deco')
@include('storefront.themes.home-deco-cart-drawer')
@else
@php
    $itemCount = $items->sum('quantity');
    $shippingThreshold = (int) ($shippingProgressThreshold ?? 0);
    $hasShippingProgress = $shippingThreshold > 0;
    $amountToFreeShipping = $hasShippingProgress ? max(0, $shippingThreshold - $summary['subtotal']) : 0;
    $shippingProgress = $hasShippingProgress ? min(100, (int) round(($summary['subtotal'] / $shippingThreshold) * 100)) : 0;
    $returnPolicy = app(\App\Support\ReturnPolicySettings::class)->get();
@endphp

<section id="cart-drawer-content" data-cart-count="{{ $itemCount }}" class="flex h-full flex-col bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
    <header class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/60">
        <div class="flex items-center gap-2">
            <i data-lucide="shopping-bag" class="h-5 w-5 text-amber-600 dark:text-amber-400"></i>
            <h2 id="cart-drawer-title" class="font-serif text-base font-bold text-zinc-900 dark:text-zinc-100">Studio Cart ({{ $itemCount }})</h2>
        </div>
        <button type="button" data-cart-drawer-close aria-label="Close cart drawer" class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
            <i data-lucide="x" class="h-5 w-5"></i>
        </button>
    </header>

    <div class="space-y-2 border-b border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
        <div class="flex items-center justify-between gap-3 text-xs">
            <p class="flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-200">
                <i data-lucide="truck" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"></i>
                @if($hasShippingProgress && $amountToFreeShipping === 0)
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">You unlocked complimentary shipping!</span>
                @elseif($hasShippingProgress)
                    <span>Add <strong class="text-amber-600 dark:text-amber-300">@storefrontMoney($amountToFreeShipping)</strong> more for Free Shipping</span>
                @elseif(! $shippingEstimateAvailable)
                    <span>Shipping calculated at checkout</span>
                @else
                    <span>Shipping estimate ready</span>
                @endif
            </p>
            @if($hasShippingProgress)<span class="shrink-0 font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $shippingProgress }}%</span>@endif
        </div>
        @if($hasShippingProgress)<div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800"><div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-emerald-500 transition-all duration-500" style="width: {{ $shippingProgress }}%"></div></div>@endif
        @if($hasShippingProgress)<p class="text-[10px] text-zinc-500 dark:text-zinc-400">Free shipping applies to eligible delivery regions.</p>@endif
    </div>

    <div class="flex-1 space-y-4 overflow-y-auto p-5" data-cart-drawer-lines>
        @forelse($items as $item)
            @php($variant = $item['variant'])
            @php($product = $variant->product)
            @php($image = $product->images->first())
            <article class="flex items-center gap-3.5 rounded-xl border border-zinc-200 bg-zinc-50 p-3.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-900">
                    @if($image)<img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" class="h-full w-full object-cover" />@endif
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="truncate text-xs font-bold text-zinc-900 dark:text-white">{{ $product->title }}</h3>
                        <form method="POST" action="{{ route('cart.items.destroy', $variant) }}" data-cart-drawer-form>
                            @csrf @method('DELETE')
                            <button type="submit" aria-label="Remove {{ $product->title }}" class="p-0.5 text-zinc-500 transition hover:text-rose-500 dark:hover:text-rose-400"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                        </form>
                    </div>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $variant->color_name ?: $variant->title }} • SKU: <span class="font-mono">{{ $variant->sku }}</span></p>
                    <div class="flex items-center justify-between pt-1">
                        <form method="POST" action="{{ route('cart.items.update', $variant) }}" data-cart-drawer-form class="flex items-center rounded-lg border border-zinc-200 bg-white p-0.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            @csrf @method('PATCH')
                            <input type="hidden" name="quantity" value="{{ max(1, $item['quantity'] - 1) }}" />
                            <button type="submit" aria-label="Decrease quantity" class="grid h-5 w-5 place-items-center text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"><i data-lucide="minus" class="h-3 w-3"></i></button>
                        </form>
                        <span class="-mx-7 w-6 text-center font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ $item['quantity'] }}</span>
                        <form method="POST" action="{{ route('cart.items.update', $variant) }}" data-cart-drawer-form class="flex items-center rounded-lg border border-zinc-200 bg-white p-0.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            @csrf @method('PATCH')
                            <input type="hidden" name="quantity" value="{{ min($item['available'], $item['quantity'] + 1) }}" />
                            <button type="submit" aria-label="Increase quantity" @disabled($item['quantity'] >= $item['available']) class="grid h-5 w-5 place-items-center text-zinc-500 transition hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-400 dark:hover:text-white"><i data-lucide="plus" class="h-3 w-3"></i></button>
                        </form>
                        <span class="font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">@storefrontMoney($variant->price * $item['quantity'])</span>
                    </div>
                </div>
            </article>
        @empty
            <div class="space-y-4 py-16 text-center">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500"><i data-lucide="shopping-bag" class="h-6 w-6"></i></div>
                <div><h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Your cart is currently empty</h3><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Discover handcrafted studio audio and workspace essentials.</p></div>
                <a href="{{ route('storefront.index') }}" data-cart-drawer-close class="inline-block rounded-xl bg-zinc-900 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-amber-400 hover:text-zinc-950 dark:bg-zinc-100 dark:text-zinc-950">Explore Collection</a>
            </div>
        @endforelse

        @if($items->isNotEmpty() && $addons->isNotEmpty())
            <section class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <p class="block font-mono text-[11px] font-semibold uppercase text-amber-600 dark:text-amber-400">Curated Companion Add-ons</p>
                @foreach($addons as $addon)
                    @php($addonVariant = $addon->variants->first())
                    @if($addonVariant)
                        <article class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-2.5 shadow-sm dark:border-zinc-800/80 dark:bg-zinc-950">
                            @if($addon->images->first())<img src="{{ $addon->images->first()->path }}" alt="{{ $addon->images->first()->alt ?: $addon->title }}" class="h-10 w-10 shrink-0 rounded-lg border border-zinc-200 object-cover dark:border-zinc-800" />@endif
                            <div class="min-w-0 flex-1"><h3 class="truncate text-xs font-medium text-zinc-900 dark:text-white">{{ $addon->title }}</h3><p class="mt-1 font-mono text-[11px] text-amber-600 dark:text-amber-400">@storefrontMoney($addonVariant->price)</p></div>
                            <form method="POST" action="{{ route('cart.items.store') }}" data-cart-drawer-form>
                                @csrf <input type="hidden" name="variant_id" value="{{ $addonVariant->id }}" /><input type="hidden" name="quantity" value="1" />
                                <button type="submit" class="flex shrink-0 items-center gap-1 rounded-lg bg-zinc-200 px-2.5 py-1 text-[11px] font-semibold text-zinc-800 transition hover:bg-zinc-300 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"><i data-lucide="plus" class="h-3 w-3"></i>Add</button>
                            </form>
                        </article>
                    @endif
                @endforeach
            </section>
        @endif
    </div>

    @if($items->isNotEmpty())
        <footer class="space-y-4 border-t border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/80">
@include('storefront.partials.cart-drawer-promo')
            @if($coupon)<div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-xs text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300"><span class="flex items-center gap-1.5"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"></i><b class="font-mono">{{ $coupon->code }}</b> applied</span><form method="POST" action="{{ route('cart.coupon.remove') }}" data-cart-drawer-form>@csrf @method('DELETE')<button type="submit" class="text-zinc-500 underline transition hover:text-rose-500 dark:text-zinc-400 dark:hover:text-rose-400">Remove</button></form></div>@endif
            @php($taxQuote = $summary['tax_quote'] ?? [])
            @php($taxRowVisible = ($taxQuote['status'] ?? 'not_configured') !== 'not_configured')
            @php($taxRate = rtrim(rtrim(number_format((float) ($taxQuote['rate_percent'] ?? 0), 4, '.', ''), '0'), '.'))
            @php($taxLabel = ($taxQuote['label'] ?? 'Tax').' ('.($taxRate === '' ? '0' : $taxRate).'%)')
            <dl class="space-y-1.5 border-t border-zinc-200 pt-2 font-mono text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"><div class="flex justify-between"><dt>Subtotal</dt><dd class="text-zinc-800 dark:text-zinc-200">@storefrontMoney($summary['subtotal'])</dd></div>@if($coupon)<div class="flex justify-between text-emerald-600 dark:text-emerald-400"><dt>Coupon Discount</dt><dd>-@storefrontMoney($summary['discount'])</dd></div>@endif<div class="flex justify-between"><dt>Estimated Shipping</dt><dd class="{{ $shippingEstimateAvailable && $summary['shipping'] === 0 ? 'font-bold text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }}">{{ ! $shippingEstimateAvailable ? 'Shipping calculated at checkout' : ($summary['shipping'] === 0 ? 'FREE' : app(\App\Support\StorefrontCurrencyContext::class)->format($summary['shipping'])) }}</dd></div>@if($taxRowVisible)<div class="flex justify-between" data-store-tax-row><dt data-store-tax-label>{{ $taxLabel }}</dt><dd data-store-tax-amount class="text-zinc-800 dark:text-zinc-200">@storefrontMoney($summary['tax'])</dd></div>@endif<div class="flex justify-between border-t border-zinc-200 pt-2 text-sm font-bold text-zinc-900 dark:border-zinc-800 dark:text-white"><dt>Estimated Total</dt><dd class="font-serif text-base text-amber-600 dark:text-amber-400">@storefrontMoney($summary['total'])</dd></div></dl>
            <a href="{{ route('checkout.create') }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 py-3.5 text-xs font-bold text-white shadow-lg transition hover:bg-amber-400 hover:text-zinc-950 dark:bg-zinc-100 dark:text-zinc-950 sm:text-sm"><span>Proceed to Checkout</span><i data-lucide="arrow-right" class="h-4 w-4"></i></a>
            <p class="flex items-center justify-center gap-3 font-mono text-[10px] text-zinc-500 dark:text-zinc-400"><span class="flex items-center gap-1"><i data-lucide="lock-keyhole" class="h-3 w-3 text-amber-600 dark:text-amber-400"></i>256-Bit Encrypted</span>@if($returnPolicy['enabled'])<span>•</span><span>{{ $returnPolicy['returnWindowDays'] }}-Day Money-Back Guarantee</span>@endif</p>
        </footer>
    @endif
</section>

@endif

@endif

@endif

@endif

@endif