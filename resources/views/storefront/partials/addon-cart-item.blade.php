@php
    $itemAddonSnapshot = $summary['promotion_snapshot'] ?? [];
    $itemAddonSaving = ($itemAddonSnapshot['type'] ?? null) === 'addon' ? (int) ($itemAddonSnapshot['allocations'][(string) $variant->id] ?? 0) : 0;
@endphp
@if($itemAddonSaving > 0)
<div class="cart-item-addon cart-item-addon-saving" data-addon-item-savings="{{ $variant->id }}">
    @include('storefront.partials.promotion-icon', ['promotionIcon' => 'addon'])
    <strong>Add-on savings · @storefrontMoney($itemAddonSaving)</strong>
</div>
@endif
@foreach(($drawerAddonOffers[$product->id] ?? []) as $itemAddonOffer)
@continue(isset($items) && !app(\App\Services\Promotions\AddonOfferService::class)->hasRemainingSlots($itemAddonOffer,$items,$itemAddonSnapshot))
<div class="cart-item-addon" data-addon-item-offer="{{ $variant->id }}">
    @include('storefront.partials.promotion-icon', ['promotionIcon' => 'addon'])
    <span>Add-on deal</span>
    <a href="{{ route('storefront.products.show', $product->handle) }}#addon-deal-{{ $itemAddonOffer->id }}" aria-label="Choose add-on: {{ $itemAddonOffer->name }}">Choose add-on →</a>
</div>
@endforeach

@php
 $giftSnapshot=$summary['promotion_snapshot']??[];
 $giftSaving=($giftSnapshot['type']??null)==='gift'?(int)($giftSnapshot['allocations'][(string)$variant->id]??0):0;
 $giftOffers=app(\App\Services\Promotions\FreeGiftOfferService::class)->forProduct($product);
@endphp
@if($giftSaving>0)
<div class="cart-item-addon cart-item-addon-saving">@include('storefront.partials.promotion-icon',['promotionIcon'=>'gift'])<strong>Free gift · @storefrontMoney($giftSaving) saved</strong></div>
@endif
@foreach($giftOffers as $giftOffer)
@continue(isset($items) && !app(\App\Services\Promotions\FreeGiftOfferService::class)->hasRemainingSlots($giftOffer,$items,$giftSnapshot))
<div class="cart-item-addon">@include('storefront.partials.promotion-icon',['promotionIcon'=>'gift'])<span>Buy {{ $giftOffer->buy_quantity }}, get {{ $giftOffer->gift_quantity }} free</span><a href="{{ route('storefront.products.show',$product->handle) }}#free-gift-{{ $giftOffer->id }}">Choose gift &rarr;</a></div>
@endforeach
