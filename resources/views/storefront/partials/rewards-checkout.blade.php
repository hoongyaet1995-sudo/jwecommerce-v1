@php
    $rewardQuote = $summary['rewards'] ?? null;
    $rewardRules = app(\App\Services\Rewards\RewardsWallet::class)->rules();
    $rewardCustomer = auth('customer')->user()?->customer;
    $rewardAvailable = ($rewardQuote['available_coins'] ?? 0) > 0;
    $rewardApplied = ($rewardQuote['coins'] ?? 0) > 0;
@endphp
@if(($rewardRules['enabled'] ?? false) && !isset($quotationVersion))
<link rel="stylesheet" href="{{ asset('css/rewards-checkout-cards.css') }}?v={{ filemtime(public_path('css/rewards-checkout-cards.css')) }}">
<section class="store-rewards store-rewards-checkout" data-rewards-checkout aria-label="Your rewards">
    <style>
[data-rewards-checkout] .rewards-checkout-heading h2{font-size:16px!important;font-weight:600!important;line-height:1.3!important;margin:0!important}
:is(.hd-theme,.bs-theme,.jg-theme,.wd-theme) [data-rewards-checkout] .rewards-checkout-heading h2{font-size:20px!important;font-weight:500!important}
</style>
    <header class="rewards-checkout-heading">
        <span class="rewards-checkout-icon">@include('storefront.partials.promotion-icon', ['promotionIcon'=>'coins'])</span>
        <div><h2>Your rewards</h2><p>{{ $rewardAvailable ? 'Available to use today' : ($rewardCustomer ? 'Earn on this order' : 'A little more to look forward to') }}</p></div>
        @if($rewardCustomer && !$rewardAvailable)<span class="rewards-checkout-balance">{{ number_format($rewardQuote['balance'] ?? 0) }} coins</span>@endif
    </header>
    @if($rewardCustomer)
        @if($rewardAvailable)
        <div class="rewards-checkout-redemption">
            <span><strong>Save @storefrontMoney($rewardQuote['available_amount'])</strong><small>Use {{ number_format($rewardQuote['available_coins']) }} coins</small></span>
            <button form="checkout-rewards-preferences" name="use_rewards" value="{{ $rewardApplied ? '0' : '1' }}" type="submit" class="store-rewards-switch" role="switch" aria-checked="{{ $rewardApplied ? 'true' : 'false' }}" aria-label="Use rewards"><span></span></button>
        </div>
        <p class="rewards-checkout-caption">{{ number_format($rewardQuote['balance']) }} coins available · Up to {{ $rewardRules['max_discount_percent'] ?? 20 }}% of eligible items.</p>
        @else
        <p class="rewards-checkout-caption">No coins to use yet. Eligible purchases earn coins after payment is confirmed.</p>
        @endif
    @else
        <div class="rewards-checkout-guest"><p>Sign in to use your coins.</p><a href="{{ route('customer.login') }}" class="rewards-checkout-signin">Sign in</a></div>
    @endif
    @if(($rewardQuote['amount'] ?? 0) > 0)<div class="store-rewards-total"><span>Rewards applied</span><strong>−@storefrontMoney($rewardQuote['amount'])</strong></div>@endif
    @error('rewards')<p role="alert">{{ $message }}</p>@enderror
</section>
@endif
