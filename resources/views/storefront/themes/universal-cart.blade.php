<?php $drawerAddonOffers = app(\App\Services\Promotions\AddonOfferService::class)->cartOffers($items); ?>
<x-layouts.app :title="'Your cart | '.$shopBrandName">
<div class="uv-theme" data-universal-cart-page><x-storefront.nav active="cart"/>
<main class="universal-cart"><nav class="fc-crumb" aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a><span>›</span><span>Cart</span></nav><h1>Your cart</h1>
@include('storefront.partials.mix-match-progress', ['mixCompact' => true])
@include('storefront.partials.universal-page-content',['contentPage'=>'cart'])
@if($errors->any())<p class="uv-error" role="alert">{{ $errors->first() }}</p>@endif
@if(session('coupon_error'))<p class="uv-error" role="alert">{{ session('coupon_error') }}</p>@endif
@if($items->isEmpty())<section class="fc-empty"><h2>Your cart is empty</h2><a class="uv-primary" href="{{ route('storefront.index') }}">View shop</a></section>
@else<p class="fc-count">{{ $items->sum('quantity') }} {{ $items->sum('quantity')===1?'item':'items' }}</p><div class="fc-grid"><div data-cart-items-column style="min-width:0"><section class="fc-items" aria-label="Cart items">
<div class="fc-table-heading" aria-hidden="true"><span>Product</span><span>Quantity</span><span>Total</span></div>
@foreach($items as $item)
@php($variant=$item['variant'])
@php($product=$variant->product)
<article class="fc-item"><div class="fc-product-details"><a class="fc-photo" href="{{ route('storefront.products.show',$product->handle) }}"><img src="{{ $variant->image_url ?: $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->title }}"></a>
<div class="fc-info"><h2><a href="{{ route('storefront.products.show',$product->handle) }}">{{ $product->title }}</a></h2>@if(filled($product->subtitle))<p class="fc-subtitle">{{ $product->subtitle }}</p>@endif<p class="fc-option">{{ $variant->title }}</p><p class="fc-sku">SKU: {{ $variant->sku }}</p>
@if($item['available']===null)<p class="uv-error" role="status">Awaiting stock verification</p>@elseif(!$variant->enabled||$item['quantity']>$item['available'])<p class="uv-error">This selection is unavailable.</p>@endif
</div></div><div class="fc-controls"><div class="fc-stepper"><form method="POST" action="{{ route('cart.items.update',$variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ max(1,$item['quantity']-1) }}"><button @disabled($item['quantity']<=1) aria-label="Decrease {{ $product->title }} quantity">−</button></form><span>{{ $item['quantity'] }}</span><form method="POST" action="{{ route('cart.items.update',$variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ $item['quantity']+1 }}"><button @disabled(!$variant->enabled||$item['available']===null||$item['quantity']>=$item['available']) aria-label="Increase {{ $product->title }} quantity">+</button></form></div>
<form class="fc-remove" method="POST" action="{{ route('cart.items.destroy',$variant) }}">@csrf @method('DELETE')<button aria-label="Remove {{ $product->title }}"><i data-lucide="trash-2" aria-hidden="true"></i>Remove</button></form></div><strong class="fc-line-total">@storefrontMoney($variant->price*$item['quantity'])</strong><div class="cart-page-item-promotions">@include('storefront.partials.addon-cart-item')</div></article>
@endforeach</section>
</div>
<aside class="fc-summary"><h2>Order summary</h2><div class="fc-coupon"><form method="POST" action="{{ route('cart.coupon.apply') }}">@csrf<input name="code" aria-label="Coupon code" placeholder="Enter coupon code" required><button class="uv-primary">Apply</button></form></div><div class="fc-subtotal"><span>Subtotal ({{ $items->sum('quantity') }} {{ $items->sum('quantity')===1?'item':'items' }})</span><strong>@storefrontMoney($summary['subtotal'])</strong></div>
@if($summary['discount'])<div class="fc-subtotal"><span>{{ ($summary['promotion_snapshot']['type'] ?? null) === 'mix_match' ? 'Mix & Match savings' : (($summary['promotion_snapshot']['type'] ?? null) === 'addon' ? 'Add-on savings' : (($summary['promotion_snapshot']['type'] ?? null) === 'gift' ? 'Free gift savings' : (($summary['promotion_snapshot']['type'] ?? null) === 'tiered' ? 'Tiered savings' : 'Discount'))) }}</span><strong>−@storefrontMoney($summary['discount'])</strong></div>@endif

@if($coupon)<div class="fc-applied"><span>{{ $coupon->code }} applied</span><form method="POST" action="{{ route('cart.coupon.remove') }}">@csrf @method('DELETE')<button>Remove coupon</button></form></div>@endif
<div class="fc-shipping"><span>Shipping and taxes</span><span>Calculated at checkout</span></div><a class="fc-checkout uv-primary" href="{{ route('checkout.create') }}">Checkout</a><a class="fc-shop uv-outline" href="{{ route('storefront.index') }}">Continue shopping →</a></aside></div>@endif
</main><x-storefront.footer/></div></x-layouts.app>
