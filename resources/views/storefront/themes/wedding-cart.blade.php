<?php $drawerAddonOffers = app(\App\Services\Promotions\AddonOfferService::class)->cartOffers($items); ?>
<x-layouts.app :title="'Your cart | '.$shopBrandName">
<div class="wd-theme wd-commerce" data-wedding-cart-page><x-storefront.nav active="cart"/>
<main class="wedding-cart"><nav class="wc-crumb" aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a><span>›</span><span>Cart</span></nav><h1>Your cart</h1>
@include('storefront.partials.mix-match-progress', ['mixCompact' => true])
<p class="wc-count">{{ $items->sum('quantity') }} items</p>
@if($errors->any())<p class="wd-error" role="alert">{{ $errors->first() }}</p>@endif
@if(session('coupon_error'))<p class="wd-error" role="alert">{{ session('coupon_error') }}</p>@endif
@if($items->isEmpty())<section class="wc-empty"><h2>Your cart is empty</h2><a class="wd-primary" href="{{ route('storefront.index') }}">View shop</a></section>
@else<div class="wc-grid"><div data-cart-items-column style="min-width:0"><section class="wc-items" aria-label="Cart items"><div class="wc-table-head"><span>Product</span><span>Quantity</span><span>Total</span></div>
@foreach($items as $item)
@php($variant=$item['variant'])
@php($product=$variant->product)
<article class="wc-item"><a class="wc-photo" href="{{ route('storefront.products.show',$product->handle) }}"><img src="{{ $variant->image_url ?: $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->title }}"></a>
<div class="wc-info"><h2><a href="{{ route('storefront.products.show',$product->handle) }}">{{ $product->title }}</a></h2>@if(filled($product->subtitle))<p class="wc-subtitle">{{ $product->subtitle }}</p>@endif<p class="wc-option">{{ $variant->title }}</p><p class="wc-sku">SKU: {{ $variant->sku }}</p>
@if($item['available']===null)<p class="wd-error" role="status">Awaiting stock verification</p>@elseif(!$variant->enabled||$item['quantity']>$item['available'])<p class="wd-error">This selection is unavailable.</p>@endif
</div><div class="wc-controls"><div class="wc-stepper"><form method="POST" action="{{ route('cart.items.update',$variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ max(1,$item['quantity']-1) }}"><button @disabled($item['quantity']<=1) aria-label="Decrease {{ $product->title }} quantity">−</button></form><span>{{ $item['quantity'] }}</span><form method="POST" action="{{ route('cart.items.update',$variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ $item['quantity']+1 }}"><button @disabled(!$variant->enabled||$item['available']===null||$item['quantity']>=$item['available']) aria-label="Increase {{ $product->title }} quantity">+</button></form></div><form class="wc-remove" method="POST" action="{{ route('cart.items.destroy',$variant) }}">@csrf @method('DELETE')<button aria-label="Remove {{ $product->title }}"><i data-lucide="trash-2" aria-hidden="true"></i>Remove</button></form></div><strong class="wc-line-total">@storefrontMoney($variant->price*$item['quantity'])</strong>
<div class="cart-page-item-promotions">@include('storefront.partials.addon-cart-item')</div></article>
@endforeach</section>
</div>
<aside class="wc-summary"><h2>Order summary</h2><div class="wc-subtotal"><span>Subtotal ({{ $items->sum('quantity') }} {{ $items->sum('quantity')===1?'item':'items' }})</span><strong>@storefrontMoney($summary['subtotal'])</strong></div>
@if($summary['discount'])<div class="wc-subtotal"><span>{{ ($summary['promotion_snapshot']['type'] ?? null) === 'mix_match' ? 'Mix & Match savings' : (($summary['promotion_snapshot']['type'] ?? null) === 'addon' ? 'Add-on savings' : (($summary['promotion_snapshot']['type'] ?? null) === 'gift' ? 'Free gift savings' : (($summary['promotion_snapshot']['type'] ?? null) === 'tiered' ? 'Tiered savings' : 'Discount'))) }}</span><strong>−@storefrontMoney($summary['discount'])</strong></div>@endif
<details class="wc-coupon" open><summary>Add coupon code<i data-lucide="chevron-down" aria-hidden="true"></i></summary><form method="POST" action="{{ route('cart.coupon.apply') }}">@csrf<input name="code" aria-label="Coupon code" placeholder="Enter coupon code" required><button class="wd-primary">Apply</button></form></details>
@if($coupon)<div class="wc-applied"><span>{{ $coupon->code }} applied</span><form method="POST" action="{{ route('cart.coupon.remove') }}">@csrf @method('DELETE')<button>Remove coupon</button></form></div>@endif
<div class="wc-total"><span>Total</span><strong>@storefrontMoney($summary['total'])</strong></div><div class="wc-shipping"><span>Shipping and taxes</span><span>Calculated at checkout</span></div><a class="wc-checkout wd-primary" href="{{ route('checkout.create') }}">Checkout</a><a class="wc-shop" href="{{ route('storefront.index') }}">Continue shopping →</a></aside></div>@endif
@php($cartMedia=app(\App\Services\WebsiteThemeCustomization::class)->media('wedding','cart',asset('images/themes/wedding/hero.png')))
@if(!$cartMedia['hidden'])<section class="wc-art" data-cart-page-media>@include('storefront.partials.theme-media',['media'=>$cartMedia])</section>@endif
</main><x-storefront.footer/></div></x-layouts.app>
