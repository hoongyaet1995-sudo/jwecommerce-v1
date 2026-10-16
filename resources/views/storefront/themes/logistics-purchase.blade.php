@if($product->showsPrice())<div class="lg-price"><strong data-pdp-price>@productPrice($product,$price)</strong><del data-pdp-compare-at @class(['hidden'=>$compare<=$price])>@if($compare>$price)@storefrontMoney($compare)@endif</del></div>@elseif($product->purchase_mode==='quote')<p class="lg-price">Price on request</p>@endif
@if($product->isBuyMode())
<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}">
<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" presentation="baby">
<div class="lg-quantity" x-data="{quantity:1}"><label for="lg-quantity">Quantity</label><div class="lg-stepper"><button type="button" @click="quantity=Math.max(1,quantity-1)" aria-label="Decrease quantity">−</button><input id="lg-quantity" name="quantity" type="number" x-model.number="quantity" value="1" min="1" max="{{ max(1,$available??1) }}"><button type="button" @click="quantity=Math.min(Number(document.getElementById('lg-quantity').max),quantity+1)" aria-label="Increase quantity">+</button></div></div>
</x-storefront.variant-selector>
<p class="lg-small">SKU <span data-pdp-sku>{{ $selectedVariant?->sku }}</span></p>
<button data-pdp-add-to-cart class="lg-primary" @disabled(!$canAdd)>{{ $canAdd?'Add to cart':($available===null?'Awaiting stock verification':'Unavailable') }}</button>
<button data-pdp-instant-buy class="lg-outline" name="checkout" value="1" @disabled(!$canAdd)>Buy now →</button></form>
@elseif($product->purchase_mode==='quote')
<div class="lg-purchase-intro"><h3>Discuss your requirements</h3><p>Tell us your quantity, intended use and delivery location. Our team will confirm availability and prepare a quotation.</p></div>
@include('storefront.partials.product-enquiry-actions',['actionClass'=>'lg-primary','optionPresentation'=>'baby'])
<button class="lg-outline" type="button" data-logistics-support>Talk to Our Team</button><p class="lg-small">No payment required to enquire.</p>
@endif
