<x-layouts.app :title="'Your cart | '.$shopBrandName">
<div class="bk-theme" data-baby-cart-page><x-storefront.nav active="cart"/>
<main class="baby-cart"><nav class="bc-crumb" aria-label="Breadcrumb"><a href="{{ route('storefront.index') }}">Shop</a><span>›</span><span>Cart</span></nav><h1>Your cart</h1>
@if($errors->any())<p class="bk-error" role="alert">{{ $errors->first() }}</p>@endif
@if(session('coupon_error'))<p class="bk-error" role="alert">{{ session('coupon_error') }}</p>@endif
@if($items->isEmpty())<section class="bc-empty"><h2>Your cart is empty</h2><a class="bk-primary" href="{{ route('storefront.index') }}">View shop</a></section>
@else<div class="bc-grid"><section class="bc-items" aria-label="Cart items">
@foreach($items as $item)
@php($variant=$item['variant'])
@php($product=$variant->product)
<article class="bc-item"><a class="bc-photo" href="{{ route('storefront.products.show',$product->handle) }}"><img src="{{ $variant->image_url ?: $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->title }}"></a>
<div class="bc-info"><h2><a href="{{ route('storefront.products.show',$product->handle) }}">{{ $product->title }}</a></h2>@if(filled($product->subtitle))<p class="bc-subtitle">{{ $product->subtitle }}</p>@endif<p class="bc-option">{{ $variant->title }}</p><p class="bc-sku">SKU: {{ $variant->sku }}</p>
@if($item['available']===null)<p class="bk-error" role="status">Awaiting stock verification</p>@elseif(!$variant->enabled||$item['quantity']>$item['available'])<p class="bk-error">This selection is unavailable.</p>@endif
<div class="bc-controls"><div class="bc-stepper"><form method="POST" action="{{ route('cart.items.update',$variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ max(1,$item['quantity']-1) }}"><button @disabled($item['quantity']<=1) aria-label="Decrease {{ $product->title }} quantity">−</button></form><span>{{ $item['quantity'] }}</span><form method="POST" action="{{ route('cart.items.update',$variant) }}">@csrf @method('PATCH')<input type="hidden" name="quantity" value="{{ $item['quantity']+1 }}"><button @disabled(!$variant->enabled||$item['available']===null||$item['quantity']>=$item['available']) aria-label="Increase {{ $product->title }} quantity">+</button></form></div><strong class="bc-line-total">@storefrontMoney($variant->price*$item['quantity'])</strong></div>
<form class="bc-remove" method="POST" action="{{ route('cart.items.destroy',$variant) }}">@csrf @method('DELETE')<button aria-label="Remove {{ $product->title }}"><i data-lucide="trash-2" aria-hidden="true"></i>Remove</button></form></div></article>
@endforeach</section>
<aside class="bc-summary"><h2>Order summary</h2><div class="bc-subtotal"><span>Subtotal ({{ $items->sum('quantity') }} {{ $items->sum('quantity')===1?'item':'items' }})</span><strong>@storefrontMoney($summary['subtotal'])</strong></div>
@if($summary['discount'])<div class="bc-subtotal"><span>Discount</span><strong>−@storefrontMoney($summary['discount'])</strong></div>@endif
<details class="bc-coupon" open><summary>Add coupon code<i data-lucide="chevron-down" aria-hidden="true"></i></summary><form method="POST" action="{{ route('cart.coupon.apply') }}">@csrf<input name="code" aria-label="Coupon code" placeholder="Enter coupon code" required><button class="bk-primary">Apply</button></form></details>
@if($coupon)<div class="bc-applied"><span>{{ $coupon->code }} applied</span><form method="POST" action="{{ route('cart.coupon.remove') }}">@csrf @method('DELETE')<button>Remove coupon</button></form></div>@endif
<div class="bc-shipping"><span>Shipping and taxes</span><span>Calculated at checkout</span></div><a class="bc-checkout bk-primary" href="{{ route('checkout.create') }}">Checkout</a><a class="bc-shop" href="{{ route('storefront.index') }}">View shop →</a></aside></div>@endif
@php($cartMedia=app(\App\Services\WebsiteThemeCustomization::class)->media('baby','cart',asset('images/themes/baby/hero.png')))
@if(!$cartMedia['hidden'])<section class="bc-art" data-cart-page-media>@include('storefront.partials.theme-media',['media'=>$cartMedia])</section>@endif
</main><x-storefront.footer/></div></x-layouts.app>
