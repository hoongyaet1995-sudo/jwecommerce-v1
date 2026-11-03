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
<div class="cart-item-addon" data-addon-item-offer="{{ $variant->id }}">
    @include('storefront.partials.promotion-icon', ['promotionIcon' => 'addon'])
    <span>Add-on deal</span>
    <a href="{{ route('storefront.products.show', $product->handle) }}#addon-deal-{{ $itemAddonOffer->id }}" aria-label="Choose add-on: {{ $itemAddonOffer->name }}">Choose add-on →</a>
</div>
@endforeach
