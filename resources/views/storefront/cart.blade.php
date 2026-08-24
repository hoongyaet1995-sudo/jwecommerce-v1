<x-layouts.app title="Studio Cart | JW Atelier">
    @php
        $itemCount = $items->sum('quantity');
        $shippingThreshold = 15000;
        $amountToFreeShipping = max(0, $shippingThreshold - $summary['subtotal']);
        $shippingProgress = min(100, (int) round(($summary['subtotal'] / $shippingThreshold) * 100));
    @endphp

    <div class="min-h-screen bg-zinc-50 text-zinc-900 transition-colors duration-200 dark:bg-zinc-950 dark:text-zinc-100">
        <x-storefront.nav active="cart" />

        <main class="py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                    <header class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/60">
                        <div class="flex items-center gap-2">
                            <i data-lucide="shopping-bag" class="h-5 w-5 text-amber-600 dark:text-amber-400"></i>
                            <h1 class="font-serif text-xl font-bold text-zinc-900 dark:text-zinc-100">Studio Cart ({{ $itemCount }})</h1>
                        </div>
                        <a href="{{ route('storefront.index') }}" class="rounded-xl border border-zinc-200 px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-200 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800">Continue shopping</a>
                    </header>

                    @if($items->isNotEmpty())
                        <div class="space-y-2 border-b border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <p class="flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-200">
                                    <i data-lucide="truck" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"></i>
                                    @if($amountToFreeShipping === 0)
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400">You unlocked Complimentary Express Shipping!</span>
                                    @else
                                        <span>Add <strong class="text-amber-600 dark:text-amber-300">@storefrontMoney($amountToFreeShipping)</strong> more for Free Shipping</span>
                                    @endif
                                </p>
                                <span class="shrink-0 font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $shippingProgress }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800"><div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-emerald-500 transition-all duration-500" style="width: {{ $shippingProgress }}%"></div></div>
                        </div>
                    @endif

                    <div class="space-y-4 p-5">
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
                                        <h2 class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $product->title }}</h2>
                                        <form method="POST" action="{{ route('cart.items.destroy', $variant) }}">@csrf @method('DELETE')<button type="submit" aria-label="Remove {{ $product->title }}" class="p-0.5 text-zinc-500 transition hover:text-rose-500 dark:hover:text-rose-400"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form>
                                    </div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $variant->color_name ?: $variant->title }} • SKU: <span class="font-mono">{{ $variant->sku }}</span></p>
                                    <div class="flex items-center justify-between pt-2">
                                        <div class="flex items-center rounded-lg border border-zinc-200 bg-white p-0.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                            <form method="POST" action="{{ route('cart.items.update', $variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ max(1, $item['quantity'] - 1) }}" /><button type="submit" aria-label="Decrease quantity" class="grid h-6 w-6 place-items-center text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"><i data-lucide="minus" class="h-3 w-3"></i></button></form>
                                            <span class="w-7 text-center font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ $item['quantity'] }}</span>
                                            <form method="POST" action="{{ route('cart.items.update', $variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ min($variant->stock, $item['quantity'] + 1) }}" /><button type="submit" aria-label="Increase quantity" @disabled($item['quantity'] >= $variant->stock) class="grid h-6 w-6 place-items-center text-zinc-500 transition hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-400 dark:hover:text-white"><i data-lucide="plus" class="h-3 w-3"></i></button></form>
                                        </div>
                                        <strong class="font-mono text-sm text-zinc-900 dark:text-zinc-100">@storefrontMoney($variant->price * $item['quantity'])</strong>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="space-y-4 py-16 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500"><i data-lucide="shopping-bag" class="h-6 w-6"></i></div><div><h1 class="text-base font-semibold text-zinc-800 dark:text-zinc-200">Your cart is currently empty</h1><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Discover handcrafted studio audio and workspace essentials.</p></div><a href="{{ route('storefront.index') }}" class="inline-block rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-400 hover:text-zinc-950 dark:bg-zinc-100 dark:text-zinc-950">Explore Collection</a></div>
                        @endforelse

                        @if($items->isNotEmpty() && $addons->isNotEmpty())
                            <section class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                <p class="font-mono text-[11px] font-semibold uppercase text-amber-600 dark:text-amber-400">Curated Companion Add-ons</p>
                                @foreach($addons as $addon)
                                    @php($addonVariant = $addon->variants->first())
                                    @if($addonVariant)
                                        <article class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-2.5 shadow-sm dark:border-zinc-800/80 dark:bg-zinc-950"><img src="{{ $addon->images->first()?->path }}" alt="{{ $addon->title }}" class="h-10 w-10 shrink-0 rounded-lg border border-zinc-200 object-cover dark:border-zinc-800" /><div class="min-w-0 flex-1"><h2 class="truncate text-xs font-medium text-zinc-900 dark:text-white">{{ $addon->title }}</h2><p class="mt-1 font-mono text-[11px] text-amber-600 dark:text-amber-400">@storefrontMoney($addonVariant->price)</p></div><form method="POST" action="{{ route('cart.items.store') }}">@csrf <input type="hidden" name="variant_id" value="{{ $addonVariant->id }}" /><input type="hidden" name="quantity" value="1" /><button class="flex shrink-0 items-center gap-1 rounded-lg bg-zinc-200 px-2.5 py-1 text-[11px] font-semibold text-zinc-800 transition hover:bg-zinc-300 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"><i data-lucide="plus" class="h-3 w-3"></i>Add</button></form></article>
                                    @endif
                                @endforeach
                            </section>
                        @endif
                    </div>

                    @if($items->isNotEmpty())
                        <footer class="space-y-4 border-t border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/80">
                            <form method="POST" action="{{ route('cart.coupon.apply') }}" class="space-y-1.5">@csrf <div class="flex gap-2"><div class="relative min-w-0 flex-1"><i data-lucide="tag" class="absolute left-3 top-2.5 h-3.5 w-3.5 text-zinc-500"></i><label class="sr-only" for="coupon">Coupon code</label><input id="coupon" name="code" required placeholder="Coupon (e.g. WELCOME10)" class="w-full rounded-lg border border-zinc-200 bg-white py-1.5 pl-8 pr-3 font-mono text-xs uppercase text-zinc-900 placeholder:text-zinc-400 focus:border-amber-500 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-amber-400" /></div><button class="rounded-lg bg-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-800 transition hover:bg-zinc-300 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">Apply</button></div>@if(session('coupon_error')) <p class="text-[11px] text-rose-600 dark:text-rose-400">{{ session('coupon_error') }}</p> @endif</form>
                            @if($coupon) <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-xs text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300"><span><b class="font-mono">{{ $coupon->code }}</b> applied</span><form method="POST" action="{{ route('cart.coupon.remove') }}">@csrf @method('DELETE') <button class="text-zinc-500 underline transition hover:text-rose-500 dark:text-zinc-400">Remove</button></form></div> @endif
                            <dl class="space-y-1.5 border-t border-zinc-200 pt-2 font-mono text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"><div class="flex justify-between"><dt>Subtotal</dt><dd class="text-zinc-800 dark:text-zinc-200">@storefrontMoney($summary['subtotal'])</dd></div>@if($coupon) <div class="flex justify-between text-emerald-600 dark:text-emerald-400"><dt>Coupon Discount</dt><dd>-@storefrontMoney($summary['discount'])</dd></div> @endif <div class="flex justify-between"><dt>Estimated Shipping</dt><dd class="{{ $summary['shipping'] === 0 ? 'font-bold text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }}">{{ $summary['shipping'] === 0 ? 'FREE' : app(\App\Support\StorefrontCurrencyContext::class)->format($summary['shipping']) }}</dd></div><div class="flex justify-between"><dt>Estimated Tax</dt><dd class="text-zinc-800 dark:text-zinc-200">@storefrontMoney($summary['tax'])</dd></div><div class="flex justify-between border-t border-zinc-200 pt-2 text-sm font-bold text-zinc-900 dark:border-zinc-800 dark:text-white"><dt>Estimated Total</dt><dd class="font-serif text-base text-amber-600 dark:text-amber-400">@storefrontMoney($summary['total'])</dd></div></dl>
                            <a href="{{ route('checkout.create') }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 py-3.5 text-sm font-bold text-white shadow-lg transition hover:bg-amber-400 hover:text-zinc-950 dark:bg-zinc-100 dark:text-zinc-950"><span>Proceed to Checkout</span><i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                        </footer>
                    @endif
                </section>
            </div>
        </main>
    </div>
</x-layouts.app>
