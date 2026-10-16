@php
 $quickVariants=$product->purchase_mode==='quote'?$product->variants->filter(fn($variant)=>$variant->enabled!==false)->values():$product->variants;
 $quickVariant=app(\App\Services\StorefrontVariantData::class)->select($quickVariants,null,$product);
 $quickOptions=$quickVariants->map(fn($variant)=>['id'=>$variant->id,'title'=>$variant->title,'canAdd'=>app(\App\Services\StorefrontVariantData::class)->canAdd($variant,$product),'price'=>$product->showsPrice()?app(\App\Services\ProductPurchaseRules::class)->price($product,$variant->price):''])->values();
 $quickPreview=(bool)($websiteDesignPreview??false);
 $quickFixture=app(\App\Services\LogisticsEnquiryPreview::class)->allows($product);
@endphp
<div data-enquiry-context x-data="{open:false,image:@js($product->images->first()?->path),variant:@js((string)$quickVariant?->id),options:@js($quickOptions)}" @logistics-quick-view.window="if(String($event.detail.id)===@js((string)$product->id))open=true" @keydown.escape.window="open=false">
<div class="lg-quick-overlay" x-show="open" x-cloak @click.self="open=false" x-trap.inert.noscroll="open" role="dialog" aria-modal="true" aria-label="{{ $product->title }} quick view"><section class="lg-quick-panel"><button class="lg-quick-close" type="button" @click="open=false" aria-label="Close quick view">×</button><div class="lg-quick-photo"><img :src="image" alt="{{ $product->title }}"><div class="lg-quick-thumbs">@foreach($product->images->take(4) as $image)<button type="button" @click="image=@js($image->path)" aria-label="View image {{ $loop->iteration }}"><img src="{{ $image->path }}" alt="{{ $image->alt ?: $product->title }}" loading="lazy"></button>@endforeach</div></div>
<div class="lg-quick-copy">@if($product->category)<p class="lg-eyebrow">{{ $product->category->name }}</p>@endif<h2>{{ $product->title }}</h2><p>{{ $product->subtitle }}</p><p>{{ \Illuminate\Support\Str::limit(strip_tags($product->description),240) }}</p><div class="lg-price">@if($product->showsPrice())<span x-text="options.find(v=>String(v.id)===String(variant))?.price">@productPrice($product,$quickVariant?->price??$product->base_price)</span>@elseif($product->purchase_mode==='quote')Price on request @endif</div>
@if($product->isBuyMode())<form method="POST" action="{{ route('cart.items.store') }}">@csrf<label>Option<select name="variant_id" x-model="variant">@foreach($product->variants as $variant)<option value="{{ $variant->id }}">{{ $variant->title }}</option>@endforeach</select></label><input type="hidden" name="quantity" value="1"><button class="lg-primary" :disabled="!options.find(v=>String(v.id)===String(variant))?.canAdd" @disabled($quickPreview)>Add to cart</button></form>
@elseif($product->purchase_mode==='quote')
@if($quickVariants->count()>1)<label>Option<select x-model="variant" data-enquiry-quick-variant>@foreach($quickVariants as $variant)<option value="{{ $variant->id }}" data-sku="{{ $variant->sku }}">{{ $variant->title }}</option>@endforeach</select></label>@else<input type="hidden" data-enquiry-quick-variant value="{{ $quickVariant?->id }}" data-sku="{{ $quickVariant?->sku }}" data-title="{{ $quickVariant?->title }}">@endif
<button type="button" class="lg-primary" data-product-enquiry @click="open=false" @disabled(($quickPreview&&!$quickFixture)||(!$quickFixture&&!config('product_enquiries.enabled')))>Request a quote →</button>
@endif
<a class="lg-outline" href="{{ route('storefront.products.show',$product->handle) }}">View full details →</a></div></section></div>
@if($product->purchase_mode==='quote'&&(!$quickPreview||$quickFixture))
@include('storefront.enquiries.style')
<dialog class="eq-dialog eq-ui" data-enquiry-dialog aria-labelledby="enquiry-title-{{ $product->id }}">@include('storefront.enquiries.form',['selectedVariant'=>$quickVariant,'enquiryModal'=>true,'enquiryTitleId'=>'enquiry-title-'.$product->id,'enquiryReturnToProduct'=>true,'enquiryPreview'=>$quickFixture])</dialog>
@endif
</div>
