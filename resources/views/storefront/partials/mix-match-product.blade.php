@foreach(app(\App\Services\Promotions\StorefrontOfferService::class)->forProduct($product) as $mixOffer)
<section class="pdp-addon-offer pdp-mix-offer" data-mix-match-product aria-label="Mix & Match">
 <div class="pdp-addon-heading"><div><small>@include('storefront.partials.promotion-icon', ['promotionIcon' => 'mix']) MIX &amp; MATCH</small><h3>{{ $mixOffer->name }}</h3></div></div>
 <p class="pdp-addon-note">Pick any {{ $mixOffer->quantity }} participating items for <strong>@storefrontMoney($mixOffer->price)</strong>.</p>
 <a class="pdp-promotion-link" href="{{ route('storefront.offers.mix-match',$mixOffer->id) }}">View offer <span aria-hidden="true">â†’</span></a>
</section>
@endforeach

@include('storefront.partials.tiered-product')
@include('storefront.partials.rewards-estimate')
