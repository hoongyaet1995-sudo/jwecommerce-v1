@foreach(app(\App\Services\Promotions\AddonOfferService::class)->forProduct($product) as $addonOffer)
<section id="addon-deal-{{ $addonOffer->id }}" class="pdp-addon-offer" data-addon-offer aria-label="Add-on deal">
 <div class="pdp-addon-heading"><div><small>@include('storefront.partials.promotion-icon', ['promotionIcon' => 'addon']) ADD-ON DEAL</small><h3>{{ $addonOffer->name }}</h3></div><span>Buy 1, add 1</span></div>
 @foreach($addonOffer->extras as $extraIndex=>$extra)
 <form method="POST" action="{{ route('cart.addon.store') }}" class="pdp-addon-row" @if($extraIndex>=3) data-addon-more hidden @endif>
 @csrf<input type="hidden" name="offer_id" value="{{ $addonOffer->id }}"><input type="hidden" name="main_variant_id" data-addon-main value="{{ $selectedVariant?->id }}"><input type="hidden" name="expected_price" value="{{ $extra['price'] }}">
 @if($extra['product']->images->first())<img loading="lazy" src="{{ $extra['product']->images->sortBy('position')->first()->path }}" alt="{{ $extra['product']->title }}">@endif
 <div class="pdp-addon-info"><a href="{{ route('storefront.products.show',$extra['product']->handle) }}">{{ $extra['product']->title }}</a><div><del data-addon-original>@storefrontMoney($extra['variants']->first()->price)</del> <strong>@storefrontMoney($extra['price'])</strong></div>
 @if($extra['variants']->count()>1)<select name="addon_variant_id" aria-label="Choose {{ $extra['product']->title }} option" required><option value="">Choose an option</option>@foreach($extra['variants'] as $av)<option value="{{ $av->id }}" data-price="{{ $av->price }}">{{ $av->title }} · @storefrontMoney($av->price)</option>@endforeach</select>@else<input type="hidden" name="addon_variant_id" value="{{ $extra['variants']->first()->id }}">@endif</div>
 <button type="submit">Add <span aria-hidden="true">+</span></button>
 </form>
 @endforeach
 @if($addonOffer->extras->count()>3)<button type="button" class="pdp-addon-expand">Show more add-ons</button>@endif
 <p class="pdp-addon-note">Add a qualifying item with your extra. Up to {{ $addonOffer->per_order_cap }} per order. Best eligible offer applies at checkout.</p>
</section>
@endforeach
