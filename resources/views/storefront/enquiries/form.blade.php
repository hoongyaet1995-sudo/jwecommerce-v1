@php
 $eqErrors=$errors->getBag('enquiry');
 $eqOld=(int)old('product_id')===(int)$product->id;
 $eqCustomer=($enquiryPreview??false)?null:auth('customer')->user();
@endphp
<div class="eq-card">
<header class="eq-heading"><p class="eq-eyebrow">Let’s talk about what you need</p><h1 id="{{ $enquiryTitleId ?? 'enquiry-title' }}">Request a quote</h1><p class="eq-lead">Tell us a little about your requirements. Our team will follow up with you.</p>@if($enquiryModal??false)<button type="button" class="eq-close" data-enquiry-close aria-label="Close enquiry form">×</button>@endif</header>
<div class="eq-body">
<div class="eq-product"><img src="{{ $selectedVariant?->image_url ?: $product->images->first()?->path ?: asset('images/image-not-found.png') }}" alt="{{ $product->title }}"><div><small>You’re enquiring about</small><h2>{{ $product->title }}</h2><p data-enquiry-option-label>{{ $selectedVariant?->title }}@if($selectedVariant?->sku) · SKU: {{ $selectedVariant->sku }}@endif</p></div></div>
@if($eqOld&&$eqErrors->any())<div class="eq-errors" role="alert">@foreach($eqErrors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('enquiries.store') }}" data-enquiry-form>@csrf
<fieldset @disabled($enquiryPreview??false) style="border:0;padding:0;margin:0;min-width:0">
@if($enquiryReturnToProduct??false)<input type="hidden" name="enquiry_context" value="product">@endif
<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="variant_id" data-enquiry-variant value="{{ $eqOld?old('variant_id'):$selectedVariant?->id }}"><input type="hidden" name="request_key" value="{{ $eqOld?old('request_key', (string)\Illuminate\Support\Str::uuid()):(string)\Illuminate\Support\Str::uuid() }}">
<div class="eq-grid">
<label class="eq-field">Full name<input name="name" autocomplete="name" placeholder="Your name" required maxlength="120" value="{{ $eqOld?old('name'):$eqCustomer?->name }}"></label>
<label class="eq-field">Email address<input name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="254" value="{{ $eqOld?old('email'):$eqCustomer?->email }}"></label>
<details class="eq-wide eq-phone" @if($eqOld&&old('phone')) open @endif><summary>Add phone number <span class="eq-optional">(optional)</span></summary><label class="eq-field"><span class="sr-only">Phone number</span><input name="phone" type="tel" autocomplete="tel" placeholder="Include your country code" maxlength="40" value="{{ $eqOld?old('phone'):'' }}"></label></details>
<label class="eq-field eq-wide">What do you need?<textarea name="requirements" placeholder="Tell us what you’re looking for, your preferred quantity or any special requirements." required maxlength="2000">{{ $eqOld?old('requirements'):'' }}</textarea><p class="eq-hint">For a service, you can include your preferred date and location.</p></label>
</div><p class="eq-contact-note">We’ll use these details to respond to your enquiry. No account needed.</p>
<div class="eq-actions">@if($enquiryModal??false)<button class="eq-cancel" type="button" data-enquiry-close>Cancel</button>@else<a class="eq-cancel" href="{{ route('storefront.products.show',$product->handle) }}">Cancel</a>@endif<button class="eq-primary" type="submit">Send enquiry <span aria-hidden="true">→</span></button></div>
</fieldset></form></div><div class="eq-bottom">An enquiry is a conversation starter. <strong>No payment required.</strong></div></div>
