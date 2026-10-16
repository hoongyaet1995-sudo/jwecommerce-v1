@php
 $receiptMedia=app(\App\Services\WebsiteThemeCustomization::class)->media('logistics','enquiry',asset('images/themes/logistics/enquiry.png'));
 $receiptDemo=(bool)($websiteThemeDemo??false);
 $receiptImage=$enquiry->product_snapshot['image']??null;
@endphp
<div class="lg-theme lg-enquiry-receipt" data-logistics-enquiry-receipt>
<x-storefront.nav active="enquiry"/>
<header class="lgr-hero {{ $receiptMedia['hidden']?'lgr-no-media':'' }}">
@if(!$receiptMedia['hidden'])<div class="lgr-hero-media">@include('storefront.partials.theme-media',['media'=>$receiptMedia])</div>@endif
<x-storefront.content-frame>
<div class="lgr-back"><a href="{{ route('storefront.index') }}">← Back to containers</a></div>
<div class="lgr-hero-copy"><svg class="lgr-check" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><circle cx="32" cy="32" r="28"/><path d="m20 32 8 8 17-19" stroke-linecap="round" stroke-linejoin="round"/></svg><div><p class="lg-eyebrow">Enquiry received</p><h1>Thank you. We’ve received your enquiry.</h1><p class="lgr-lead">{{ $receiptDemo?'Demo only. No enquiry has been submitted or saved.':'Our team will review your requirements and contact you using the details provided.' }}</p><div class="lgr-reference"><span>Enquiry reference</span><strong>{{ $enquiry->reference }}</strong></div></div></div>
</x-storefront.content-frame></header>
<main class="lgr-main" @if(request()->boolean('support') && $enquiry->support_conversation_id && session('support_enquiry_id') === $enquiry->id) data-enquiry-support-open @endif>
<x-storefront.content-frame><section class="lgr-details"><div class="lgr-request"><p class="lg-eyebrow">Your enquiry</p><div class="lgr-product">@if(filled($receiptImage))<img src="{{ $receiptImage }}" alt="{{ $enquiry->product_title }}">@endif<div><h2>{{ $enquiry->product_title }}</h2>@if(filled($enquiry->product_snapshot['variant']??null))<p>{{ $enquiry->product_snapshot['variant'] }}</p>@endif</div></div><div class="lgr-requirements"><h3>Your requirements</h3><p>{{ $enquiry->requirements }}</p></div></div><aside class="lgr-contact"><h2>Contact details</h2><dl><div><dt>Name</dt><dd>{{ $enquiry->name }}</dd></div><div><dt>Email</dt><dd>{{ $enquiry->email }}</dd></div><div><dt>Phone</dt><dd>{{ $enquiry->phone?:'Not provided' }}</dd></div></dl></aside></section>
@if($quotationVersions->isNotEmpty())<section class="lgr-quotations"><h2>Your quotations</h2>@foreach($quotationVersions as $quoteVersion)<p><a href="{{ route('quotations.show',$quoteVersion) }}">{{ $quoteVersion->document['number'] }} · v{{ $quoteVersion->revision }} ↗</a> · {{ $quoteVersion->expires_at?->isPast()?'Expired':'Sent' }}</p>@endforeach</section>@endif
<section class="lgr-next"><p>You can leave this page, or continue the conversation with our team.</p><div class="lgr-actions"><form method="POST" action="{{ route('enquiries.support',$enquiry) }}">@csrf<button class="lg-primary" type="submit" @disabled($receiptDemo)>Continue with Support <span aria-hidden="true">→</span></button></form><a class="lg-outline" href="{{ route('storefront.index') }}">Continue Browsing</a></div><p class="lgr-disclosure">Your enquiry has been {{ $receiptDemo?'shown as a demonstration':'recorded' }}. Availability, pricing and delivery arrangements are subject to confirmation.</p></section>
</x-storefront.content-frame></main><div class="lgr-footer"><x-storefront.footer/></div></div>
