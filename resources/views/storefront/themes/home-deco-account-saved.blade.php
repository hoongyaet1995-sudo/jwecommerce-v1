@php($savedProducts=app(\App\Services\StorefrontWishlistData::class)->products($accountPayload['wishlist']))
<section x-show="activeTab==='wishlist'" x-cloak data-home-deco-saved-items><h2>Saved pieces</h2><p>{{ count($accountPayload['wishlist']) }} items</p><div class="hd-saved-grid">
@forelse($accountPayload['wishlist'] as $saved)@php($item=$saved['product']) @php($model=$savedProducts->get($item['id']))
<article><a href="{{ route('storefront.products.show',$item['handle']) }}"><img src="{{ $item['images'][0]['path'] ?? asset('images/image-not-found.png') }}" alt="{{ $item['title'] }}"><h3>{{ $item['title'] }}</h3></a><p>{{ $item['variants'][0]['title'] ?? '' }}</p><strong>@storefrontMoney($item['variants'][0]['price'] ?? $item['basePrice'])</strong>
@if($model?->variants->isNotEmpty())<button type="button" class="hd-tomato" data-quick-add-trigger data-quick-add-product-id="{{ $model->id }}" data-quick-add-variant-id="{{ $model->variants->first()->id }}">Quick buy &rarr;</button>@endif
<form method="POST" action="{{ route('customer.wishlist.destroy',$item['id']) }}">@csrf @method('DELETE')<button class="hd-link" @disabled($preview)>Remove</button></form>
</article>@empty<p class="hd-empty">No saved items yet.</p>@endforelse</div></section>
@foreach($savedProducts as $savedProduct)@if($savedProduct->variants->isNotEmpty())<x-storefront.quick-add-variant-modal :product="$savedProduct" :variants="$savedProduct->variants" :images="$savedProduct->images" theme="home_deco" />@endif@endforeach
