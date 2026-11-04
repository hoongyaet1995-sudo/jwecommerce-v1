@foreach(app(\App\Services\Promotions\TieredOfferService::class)->forProduct($product) as $tierOffer)
@php($tierRows=collect($tierOffer->tiers)->sortBy('spend'))
<section class="pdp-addon-offer" data-tiered-product aria-label="Tiered discount">
<div class="pdp-addon-heading"><div><small>@include('storefront.partials.promotion-icon',['promotionIcon'=>'tiered']) TIERED DISCOUNT</small><h3>{{ $tierOffer->name }}</h3></div></div>
@php($firstTier=$tierRows->first())
<p class="pdp-addon-note">Spend @storefrontMoney($firstTier['spend']), save @if($tierOffer->discount_type==='percentage'){{ $firstTier['discount'] }}%@else @storefrontMoney($firstTier['discount'])@endif.</p>
<details class="tiered-details"><summary>View tiers <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
@foreach($tierRows as $tier)<div class="tiered-rule"><span>Spend @storefrontMoney($tier['spend'])</span><strong>Save @if($tierOffer->discount_type==='percentage'){{ $tier['discount'] }}%@else @storefrontMoney($tier['discount'])@endif</strong></div>@endforeach
<p class="pdp-addon-note">Highest qualifying tier applies. Shipping and taxes excluded.</p></details></section>
@endforeach
