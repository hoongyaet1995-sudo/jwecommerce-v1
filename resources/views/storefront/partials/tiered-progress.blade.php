@foreach(app(\App\Services\Promotions\TieredOfferService::class)->progress($items) as $tierProgress)
@php
 $tierOffer=$tierProgress['offer'];$tierNext=$tierProgress['next'];
 $tierApplied=($summary['promotion_snapshot']['type']??null)==='tiered'&&($summary['promotion_snapshot']['offer_id']??null)===$tierOffer->id;
@endphp
@if($tierNext || $tierApplied)
<section data-tiered-progress class="tiered-cart-progress">
<strong>@include('storefront.partials.promotion-icon',['promotionIcon'=>'tiered'])
@if($tierNext)Spend @storefrontMoney($tierNext['spend']-$tierProgress['subtotal']) more to save @if($tierOffer->discount_type==='percentage'){{ $tierNext['discount'] }}%@else @storefrontMoney($tierNext['discount'])@endif
@else You save @storefrontMoney($summary['discount'])@endif</strong>
@if($tierApplied && $tierNext)<small>You save @storefrontMoney($summary['discount'])</small>@endif
</section>
@endif
@endforeach
