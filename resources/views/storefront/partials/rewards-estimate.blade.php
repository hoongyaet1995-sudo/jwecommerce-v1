@php
 $rewardsEstimate = app(\App\Services\Rewards\RewardsPresentation::class)->estimate(
   isset($summary) ? (int)($summary['subtotal'] ?? 0) : (int)($product->variants->min('price') ?? 0),
   isset($summary) ? (int)($summary['discount'] ?? 0) + (int)($summary['rewards']['amount'] ?? 0) : 0
 );
@endphp
@if($rewardsEstimate)
<section class="store-rewards" data-rewards-estimate aria-label="Coins cashback">
 <div class="store-rewards-title"><div><small>@include('storefront.partials.promotion-icon',['promotionIcon'=>'coins']) COINS CASHBACK</small></div></div>
 <strong>Earn up to {{ number_format($rewardsEstimate['coins']) }} coins</strong>
 <p class="store-rewards-note">Estimated from eligible items after discounts. Final rewards are confirmed with your order.</p>
</section>
@endif
