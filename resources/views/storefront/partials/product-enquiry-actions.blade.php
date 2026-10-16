@if($product->purchase_mode === 'quote')
<x-storefront.variant-selector :product="$product" :variants="$product->variants" :images="$images" :selected-variant="$selectedVariant" :presentation="$optionPresentation ?? 'default'" />
<button type="button" data-product-enquiry class="{{ $actionClass ?? 'rounded-xl border px-6 py-3 font-semibold' }}" @disabled(($websiteDesignPreview ?? false)||!config('product_enquiries.enabled'))>Request a quote</button>
@if($websiteDesignPreview??false)<p class="mt-3 text-sm">Demo only. Enquiries are not submitted.</p>@else
@include('storefront.enquiries.style')
<dialog class="eq-dialog eq-ui" data-enquiry-dialog aria-labelledby="enquiry-title" @if((int)old('product_id')===(int)$product->id && $errors->getBag('enquiry')->any()) data-reopen @endif>
@include('storefront.enquiries.form',['enquiryModal'=>true])
</dialog>
<noscript><a href="{{ route('enquiries.create',$product->handle) }}?variant={{ $selectedVariant?->id }}">Open enquiry form</a></noscript>
@endif
@endif
