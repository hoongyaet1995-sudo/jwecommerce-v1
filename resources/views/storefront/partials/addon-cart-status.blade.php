@if(collect(session('addon_selections', []))->contains(fn ($items) => count($items) > 0))
<div class="addon-cart-status" role="status">
 @include('storefront.partials.promotion-icon', ['promotionIcon' => 'addon'])
 @if(($summary['promotion_snapshot']['type']??null)==='addon')<strong>Add-on savings · @storefrontMoney($summary['promotion_snapshot']['discount'])</strong>
 @elseif(($summary['discount']??0)>0)<span>A better offer is applied. Add-ons are shown at their regular price.</span>
 @else<span>Add-on offer no longer applies. Items are shown at their regular price.</span>@endif
</div>
@endif
