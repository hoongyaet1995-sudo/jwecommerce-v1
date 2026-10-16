@if($product->showsPrice())<div class="wd-price"><strong data-pdp-price>@productPrice($product, $price)</strong><del data-pdp-compare-at @class(['hidden'=>$compare<=$price])>@if($compare>$price)@storefrontMoney($compare)@endif</del><span data-pdp-save @class(['hidden'=>$compare<=$price])>@if($compare>$price)Save @storefrontMoney($compare-$price)@endif</span></div>@elseif($product->purchase_mode==='quote')<p class="wd-price">Price on request</p>@endif

@if($product->isBuyMode())<form method="POST" action="{{ route('cart.items.store') }}">@csrf<input data-pdp-variant-input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}">

<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" presentation="baby">

<div class="wd-quantity" x-data="{quantity:1}"><label for="wd-quantity">Quantity</label><div class="tq-stepper"><button type="button" @click="quantity=Math.max(1,quantity-1)" aria-label="Decrease quantity">−</button><input id="wd-quantity" name="quantity" type="number" x-model.number="quantity" value="1" min="1" max="{{ max(1,$available ?? 1) }}"><button type="button" @click="quantity=Math.min(Number(document.getElementById('wd-quantity').max),quantity+1)" aria-label="Increase quantity">+</button></div></div>
</x-storefront.variant-selector>
<p class="wd-fine wd-sku">SKU <span data-pdp-sku>{{ $selectedVariant?->sku }}</span></p>

<button data-pdp-add-to-cart class="wd-primary" @disabled(!$canAdd)>{{ $canAdd?'Add to cart':($available===null?'Awaiting stock verification':'Unavailable') }}</button>

<button data-pdp-instant-buy class="wd-outline wd-buy" name="checkout" value="1" @disabled(!$canAdd)>Buy now</button>

</form>@else @include('storefront.partials.product-enquiry-actions', ['actionClass'=>'wd-primary', 'optionPresentation'=>'baby']) @endif
