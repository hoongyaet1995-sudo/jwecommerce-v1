<x-layouts.app :title="'Checkout | '.$shopBrandName">
    @php($defaultSavedAddress = collect($savedAddresses)->firstWhere('isDefaultShipping', true) ?: ($savedAddresses[0] ?? null))
    @php($selectedAddressId = old('selected_address_id', $defaultSavedAddress['id'] ?? 'custom'))
    @php($checkoutAddress = [
        'firstName' => old('first_name', $defaultSavedAddress['firstName'] ?? ''),
        'lastName' => old('last_name', $defaultSavedAddress['lastName'] ?? ''),
        'email' => old('email', $checkoutEmail ?? $defaultSavedAddress['email'] ?? ''),
        'phone' => old('phone', $defaultSavedAddress['phone'] ?? ''),
        'street' => old('line_1', $defaultSavedAddress['street'] ?? ''),
        'apartment' => old('line_2', $defaultSavedAddress['apartment'] ?? ''),
        'city' => old('city', $defaultSavedAddress['city'] ?? ''),
        'state' => old('state', $defaultSavedAddress['state'] ?? ''),
        'postalCode' => old('postal_code', $defaultSavedAddress['postalCode'] ?? ''),
        'country' => old('country', $defaultSavedAddress['country'] ?? 'MY'),
    ])
    <div class="wd-theme wd-commerce wd-checkout" data-wedding-checkout>
        <x-storefront.nav active="checkout" />
        @php($checkoutHero=app(\App\Services\WebsiteThemeCustomization::class)->media('wedding','checkout',asset('images/themes/wedding/hero.png')))
@if(!$checkoutHero['hidden'])<div class="wd-checkout-hero" data-checkout-page-media>@include('storefront.partials.theme-media',['media'=>$checkoutHero])</div>@endif
<main class="wedding-checkout"><nav class="wco-crumb"><a href="{{ route('storefront.index') }}">Shop</a><span>/</span><span>Checkout</span></nav><h1>Checkout</h1><p class="wd-checkout-intro">Almost there. Please fill in your details to complete your order.</p>

        @if($quotationCheckout ?? false)
            @include('storefront.partials.quotation-checkout-form', ['quoteGridClass'=>'wco-grid', 'quoteStepsClass'=>'wco-steps', 'quoteSidebarClass'=>'wco-sidebar', 'quoteSummaryClass'=>'wco-summary', 'quoteCardClass'=>'wco-card', 'quotePaymentClass'=>'wco-payment-choice'])
        @else
        <form id="checkout-coupon-apply-form" method="POST" action="{{ route('cart.coupon.apply') }}" class="hidden">
            @csrf
            <input type="hidden" name="return_to" value="checkout" />
        </form>
        @if($coupon)
            <form id="checkout-coupon-remove-form" method="POST" action="{{ route('cart.coupon.remove') }}" class="hidden">
                @csrf
                @method('DELETE')
                <input type="hidden" name="return_to" value="checkout" />
            </form>
        @endif
        @if(in_array($hostedReturn['state'] ?? null, ['cancelled','failed'], true) && !empty($hostedReturn['payment']))
        <form id="food-payment-retry-form" method="POST" action="{{ route('checkout.payment-retry', $hostedReturn['payment']) }}" class="hidden">@csrf</form>
        @endif
        <form method="POST" action="{{ route('checkout.store') }}" enctype="multipart/form-data" data-checkout-shipping-quote-url="{{ route('checkout.shipping-quote') }}" data-checkout-initial-shipping-quote="{{ filled($checkoutAddress['country']) && filled($checkoutAddress['postalCode']) ? 'true' : 'false' }}" class="wco-grid">
            @csrf
            <section class="wco-steps">

                <div
                    x-data="{
                        savedAddresses: @js($savedAddresses),
                        selectedAddressId: @js((string) $selectedAddressId),
                        address: @js($checkoutAddress),
                        defaultEmail: @js($checkoutEmail),
                        selectSavedAddress(saved) {
                            this.selectedAddressId = String(saved.id);
                            this.address = {
                                firstName: saved.firstName,
                                lastName: saved.lastName,
                                email: this.defaultEmail || saved.email || this.address.email,
                                phone: saved.phone || '',
                                street: saved.street,
                                apartment: saved.apartment || '',
                                city: saved.city,
                                state: saved.state,
                                postalCode: saved.postalCode,
                                country: saved.country,
                            };
                            this.$nextTick(() => window.dispatchEvent(new Event('checkout-address-changed')));
                        },
                        selectCustomAddress() { this.selectedAddressId = 'custom'; this.$nextTick(() => window.dispatchEvent(new Event('checkout-address-changed'))); }
                    }"
                    class="wco-address-state"
                >
<section class="wco-card">
                    <div class="flex items-center gap-3 border-b border-zinc-200 pb-3"><span class="grid h-7 w-7 place-items-center bg-amber-400 text-xs font-black text-zinc-950">1</span><h2>Contact information</h2></div>
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">First name</label><input name="first_name" type="text" x-model="address.firstName" class="w-full bg-zinc-50 border border-zinc-300 px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" required /></div>
                        <div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">Last name</label><input name="last_name" type="text" x-model="address.lastName" class="w-full bg-zinc-50 border border-zinc-300 px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" required /></div>
                        <div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">Email address</label><input name="email" type="email" x-model="address.email" class="w-full bg-zinc-50 border border-zinc-300 px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" required /></div>
                        <div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">Phone (optional)</label><input name="phone" type="text" x-model="address.phone" class="w-full bg-zinc-50 border border-zinc-300 px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" /></div>
                    </div>
                    </section><section class="wco-card"><div class="wco-heading"><span class="grid h-7 w-7 place-items-center bg-amber-400 text-xs font-black text-zinc-950">2</span><h2 class="font-serif text-sm font-bold text-zinc-900">Shipping address</h2></div>
                    @if($savedAddresses !== [])<details class="wd-saved-address-choice"><summary>Use a saved address</summary><select aria-label="Saved address" @change="const saved=savedAddresses.find(a=>String(a.id)===$event.target.value);saved?selectSavedAddress(saved):selectCustomAddress()"><option value="custom">Enter an address</option>@foreach($savedAddresses as $saved)<option value="{{ $saved['id'] }}" @selected((string)$selectedAddressId===(string)$saved['id'])>{{ $saved['label']?:$saved['firstName'] }}</option>@endforeach</select></details>@endif
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1 sm:col-span-2"><label class="text-xs text-zinc-600 font-medium">Address line 1</label><input name="line_1" type="text" x-model="address.street" class="w-full bg-zinc-50 border border-zinc-300 px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" required /></div>
                        <div class="space-y-1 sm:col-span-2"><label class="text-xs text-zinc-600 font-medium">Address line 2 (optional)</label><input name="line_2" type="text" x-model="address.apartment" class="w-full bg-zinc-50 border border-zinc-300 px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" /></div>
                        <div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">City</label><input name="city" type="text" x-model="address.city" class="w-full bg-zinc-50 border border-zinc-300 px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" required /></div>
                        <div class="grid grid-cols-2 gap-2"><div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">State</label><input name="state" type="text" x-model="address.state" @input="window.dispatchEvent(new Event('checkout-address-changed'))" class="w-full bg-zinc-50 border border-zinc-300 px-3 py-2 text-xs text-zinc-900 focus:outline-none focus:border-amber-500" /></div><div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">Postcode</label><input name="postal_code" type="text" x-model="address.postalCode" @input="window.dispatchEvent(new Event('checkout-address-changed'))" class="w-full bg-zinc-50 border border-zinc-300 px-3 py-2 text-xs text-zinc-900 font-mono focus:outline-none focus:border-amber-500" required /></div></div>
                        <div class="space-y-1"><label class="text-xs text-zinc-600 font-medium">Country</label><select name="country" x-model="address.country" data-storefront-country @change="window.dispatchEvent(new Event('checkout-address-changed'))" required><option value="{{ $checkoutAddress['country'] }}">{{ $checkoutAddress['country']==='MY'?'Malaysia':$checkoutAddress['country'] }}</option></select></div>

                    </div>
                    @if($errors->any())<p class="mt-4 text-xs text-red-400">Please review the highlighted delivery details.</p>@endif
                </section></div>

                <div class="wco-delivery"><x-storefront.delivery-options step="3" heading="Delivery Method" :summary="$summary" :options="$deliveryOptions" :selected="$selectedDelivery" :quote-url="route('checkout.shipping-quote')" /></div>

                @php($selectedPayment = old('payment_method', request('payment_method', array_key_first($paymentMethods))))
                @php($bankConfig = $paymentMethodModels['bank_transfer']['bankTransfer'] ?? null)
                @php($bankAccounts = $bankConfig['bankAccounts'] ?? [])
                @php($selectedBankAccount = old('bank_account_id', collect($bankAccounts)->firstWhere('isPrimary', true)['id'] ?? ($bankAccounts[0]['id'] ?? null)))
                <section
                    x-data="{ selectedPayment: @js($selectedPayment), selectedBankAccountId: @js($selectedBankAccount), copiedKey: '', proofName: '', copy(value, key) { navigator.clipboard?.writeText(value); this.copiedKey = key; setTimeout(() => this.copiedKey = '', 1600); } }"
                    class="wco-card wco-payment-card space-y-5"
                >
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-3"><div class="flex items-center gap-3"><span class="grid h-7 w-7 place-items-center bg-amber-400 text-xs font-black text-zinc-950">4</span><h2 class="font-serif text-sm font-bold text-zinc-900">Payment method</h2></div></div>

                    <div class="wd-payment-methods grid grid-cols-1 gap-2">@foreach($paymentMethods as $method => $label)<label class="wco-payment-choice"><input x-model="selectedPayment" type="radio" name="payment_method" value="{{ $method }}" class="wd-payment-radio" /><i data-lucide="{{ $method === 'bank_transfer' ? 'building-2' : ($method === 'cod' ? 'banknote' : 'credit-card') }}" class="h-3.5 w-3.5"></i><span class="truncate">{{ $label }}</span></label>@if($method==='bank_transfer')                    @if($bankConfig)<div x-show.important="selectedPayment==='bank_transfer'" x-cloak class="wd-bank-fields"><label>Bank account<select name="bank_account_id" x-model="selectedBankAccountId">@foreach($bankAccounts as $account)<option value="{{ $account['id'] }}">{{ $account['bankName'] }} ({{ $account['accountNumber'] }})</option>@endforeach</select></label>
@foreach($bankAccounts as $account)<div x-show="selectedBankAccountId===@js($account['id'])"><small>{{ $account['accountHolderName']??$account['accountHolder']??'' }}</small>@if(!empty($account['duitNowId']))<p>DuitNow: {{ $account['duitNowId'] }}</p>@endif </div>@endforeach
@if($bankConfig['allowProofUpload'])<label>Upload payment proof (optional)<input type="file" name="payment_proof" accept="image/jpeg,image/png,application/pdf" :disabled="selectedPayment!=='bank_transfer'"></label><small>You can also upload proof after your order is created.</small>@endif</div>@endif@endif @endforeach</div>

                    @if($paymentMethods === [])<p class="wd-error" role="status">No payment methods are currently available. Please contact the store before placing an order.</p>@endif

                    @if($hostedReturn)
                        <div class="space-y-4 bg-zinc-50 p-4 sm:p-5 border border-zinc-200">
                            @if($hostedReturn['state'] === 'redirecting')
                                <div class="p-4 sm:p-5 bg-white border border-zinc-200 text-center space-y-3"><div class="flex items-center justify-center gap-2"><i data-lucide="credit-card" class="w-4 h-4 text-amber-600"></i><span class="text-xs font-bold text-zinc-900">{{ $hostedReturn['provider'] }}</span></div><div class="flex flex-col items-center justify-center py-2 space-y-2"><i data-lucide="loader-circle" class="w-5 h-5 animawd-spin text-amber-600"></i><p class="text-xs font-semibold text-zinc-900">Redirecting securely to {{ $hostedReturn['provider'] }}…</p></div><p class="text-[11px] text-zinc-500">Do not close or refresh this page.</p></div>
                            @elseif($hostedReturn['state'] === 'cancelled')
                                <div class="p-4 bg-amber-500/10 border border-amber-500/30 space-y-3"><div class="flex items-start gap-3"><div class="w-7 h-7 bg-amber-500/20 text-amber-700 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="circle-alert" class="w-4 h-4"></i></div><div class="flex-1 min-w-0 space-y-1"><div class="flex items-center gap-2"><i data-lucide="credit-card" class="w-3.5 h-3.5"></i><span class="text-xs font-bold text-amber-900">{{ $hostedReturn['provider'] }}</span></div><p class="text-xs font-medium text-amber-800">Payment was cancelled. Your order has not been paid.</p></div></div><div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-amber-500/20"><span class="text-[11px] text-amber-800/90">Your items remain reserved in cart. You can retry with {{ $hostedReturn['provider'] }} or pick another method.</span><button form="food-payment-retry-form" type="submit" class="px-3 py-1.5 bg-zinc-900 hover:bg-amber-400 hover:text-zinc-950 text-white font-bold text-xs transition-all cursor-pointer shrink-0">Try again</button></div></div>
                            @elseif($hostedReturn['state'] === 'failed')
                                <div class="p-4 bg-rose-50 border border-rose-200 space-y-3"><div class="flex items-start gap-3"><div class="w-7 h-7 bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="circle-alert" class="w-4 h-4"></i></div><div class="flex-1 min-w-0 space-y-1"><div class="flex items-center gap-2"><i data-lucide="credit-card" class="w-3.5 h-3.5"></i><span class="text-xs font-bold text-rose-900">{{ $hostedReturn['provider'] }}</span></div><p class="text-xs font-medium text-rose-800">We could not confirm this payment. No charge was captured.</p></div></div><div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-rose-200"><span class="text-[11px] text-rose-700">No funds were deducted from your account. You can re-attempt checkout below.</span><button form="food-payment-retry-form" type="submit" class="px-3 py-1.5 bg-zinc-900 hover:bg-amber-400 hover:text-zinc-950 text-white font-bold text-xs transition-all cursor-pointer shrink-0">Try again</button></div></div>
                            @elseif($hostedReturn['state'] === 'pending')
                                <div class="p-4 bg-white border border-zinc-200 space-y-3"><div class="flex items-start justify-between gap-2 border-b border-zinc-200 pb-3"><div class="flex items-center gap-2"><i data-lucide="credit-card" class="w-4 h-4 text-amber-600"></i><span class="text-xs font-bold text-zinc-900 uppercase tracking-wider">{{ $hostedReturn['provider'] }} Status</span></div><span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-mono font-semibold bg-amber-500/15 text-amber-700 border border-amber-500/30"><i data-lucide="clock" class="w-2.5 h-2.5"></i><span>Pending Webhook</span></span></div><div class="space-y-1.5"><p class="text-xs font-medium text-zinc-800">Payment submitted — awaiting secure confirmation.</p><div class="flex flex-wrap items-center gap-2 pt-0.5"><span class="text-[11px] text-zinc-500">Order Reference:</span><span class="text-xs font-mono font-bold text-zinc-900 bg-zinc-100 px-2 py-0.5 border border-zinc-200">{{ $hostedReturn['reference'] }}</span></div></div><div class="pt-2 border-t border-zinc-200 flex items-start gap-1.5 text-[11px] text-zinc-500 leading-relaxed"><i data-lucide="info" class="w-3.5 h-3.5 text-zinc-400 shrink-0 mt-0.5"></i><span>Fulfillment begins only after payment confirmation.</span></div></div>
                            @endif
                        </div>
                    @endif


                </section>
            </section>

            @php($taxQuote = $summary['tax_quote'] ?? [])
            @php($taxRowVisible = ($taxQuote['status'] ?? 'not_configured') !== 'not_configured')
            @php($taxRate = rtrim(rtrim(number_format((float) ($taxQuote['rate_percent'] ?? 0), 4, '.', ''), '0'), '.'))
            @php($taxLabel = ($taxQuote['label'] ?? 'Tax').' ('.($taxRate === '' ? '0' : $taxRate).'%)')
            <aside class="wco-sidebar"><section class="wco-summary"><div class="flex items-center justify-between border-b border-zinc-200 pb-4"><h2 class="font-serif text-xl font-bold text-zinc-900">Order summary</h2><span class="font-mono text-xs text-zinc-500">{{ app(\App\Support\StorefrontCurrencyContext::class)->current()['code'] }} ({{ trim(app(\App\Support\StorefrontCurrencyContext::class)->current()['symbolLeft'].app(\App\Support\StorefrontCurrencyContext::class)->current()['symbolRight']) }})</span></div><div class="space-y-4 py-5">@foreach($items as $item) @php($image = $item['variant']->product->images->first()) <article class="flex items-center gap-3">@if($image) <img src="{{ $image->path }}" alt="{{ $item['variant']->product->title }}" class="h-14 w-14 border border-zinc-200 object-cover" /> @endif <div class="min-w-0 flex-1"><h3 class="wco-product-title">{{ $item['variant']->product->title }}</h3><p class="mt-1 truncate text-[11px] text-zinc-500">{{ $item['variant']->title }} · Qty {{ $item['quantity'] }}</p></div><strong class="font-mono text-xs text-zinc-900">@storefrontMoney($item['variant']->price * $item['quantity'])</strong></article> @endforeach</div><div data-checkout-coupon-form class="space-y-1.5 border-t border-zinc-200 py-4"><div class="flex gap-2"><div class="relative min-w-0 flex-1"><i data-lucide="tag" class="absolute left-3 top-2.5 h-3.5 w-3.5 text-zinc-500"></i><label class="sr-only" for="checkout-coupon">Coupon code</label><input id="checkout-coupon" form="checkout-coupon-apply-form" name="code" required placeholder="Enter coupon code" class="w-full border border-zinc-200 bg-white py-1.5 pl-8 pr-3 font-mono text-xs text-zinc-900 placeholder:text-zinc-400 focus:border-amber-500 focus:outline-none" /></div><button form="checkout-coupon-apply-form" type="submit" class="bg-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-800 transition hover:bg-zinc-300">Apply</button></div>@if(session('coupon_error'))<p class="flex items-center gap-1 text-[11px] text-rose-600"><i data-lucide="circle-alert" class="h-3 w-3"></i>{{ session('coupon_error') }}</p>@endif</div>@if($coupon)<div class="mb-4 flex items-center justify-between border border-emerald-200 bg-emerald-50 p-2 text-xs text-emerald-800"><span class="flex items-center gap-1.5"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-600"></i><b class="font-mono">{{ $coupon->code }}</b> applied</span><button form="checkout-coupon-remove-form" type="submit" class="text-zinc-500 underline transition hover:text-rose-500">Remove</button></div>@endif<dl class="space-y-2 border-t border-zinc-200 pt-4 font-mono text-xs text-zinc-500"><div class="flex justify-between"><dt>Subtotal</dt><dd class="text-zinc-800">@storefrontMoney($summary['subtotal'])</dd></div>@if($coupon) <div class="flex justify-between text-emerald-600"><dt>Coupon ({{ $coupon->code }})</dt><dd>-@storefrontMoney($summary['discount'])</dd></div> @endif <div class="flex justify-between"><dt>Shipping</dt><dd class="{{ $shippingEstimateAvailable && ! $summary['shipping'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }}">{{ ! $shippingEstimateAvailable ? 'Shipping calculated at checkout' : ($summary['shipping'] ? app(\App\Support\StorefrontCurrencyContext::class)->format($summary['shipping']) : 'FREE') }}</dd></div>@if($taxRowVisible)<div class="flex justify-between" data-store-tax-row><dt data-store-tax-label>{{ $taxLabel }}</dt><dd data-store-tax-amount class="text-zinc-800">@storefrontMoney($summary['tax'])</dd></div>@endif<div class="flex justify-between border-t border-zinc-200 pt-4 text-sm font-bold text-zinc-900"><dt>Total</dt><dd class="font-serif text-2xl text-amber-600">@storefrontMoney($summary['total'])</dd></div></dl><button type="submit" data-checkout-submit class="mt-6 w-full bg-zinc-900 px-5 py-4 text-sm font-black text-white transition hover:bg-amber-400 hover:text-zinc-950"><i data-lucide="lock-keyhole" class="mr-2 inline h-4 w-4"></i><span data-checkout-submit-label>Place order · @storefrontMoney($summary['total'])</span></button><p class="mt-4 text-center font-mono text-[10px] text-zinc-500">By placing this order, you agree to the Terms &amp; Conditions &amp; Privacy Policy.</p></section>@php($summaryMedia=app(\App\Services\WebsiteThemeCustomization::class)->media('wedding','checkoutSummary',asset('images/themes/wedding/album.png')))
@if(!$summaryMedia['hidden'])<div class="wd-checkout-art">@include('storefront.partials.theme-media',['media'=>$summaryMedia])</div>@endif</aside>
        </form>
        @endif
        </main><x-storefront.footer />
    </div>
</x-layouts.app>
