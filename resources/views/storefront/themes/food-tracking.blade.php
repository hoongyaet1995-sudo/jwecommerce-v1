<x-layouts.app :title="'Track your order | '.$shopBrandName"><div class="fd-theme" data-food-tracking><x-storefront.nav active="account"/>
@if($step==='verified_result'&&$trackingPayload)
@include('storefront.themes.food-tracking-result')
@else
<main class="fd-tracking fd-tracking-split"><section class="fd-tracking-content"><h1>Track your order</h1>
@if($errors->any())<p role="alert" class="fd-error">{{ $errors->first() }}</p>@endif @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($step==='verify')
<p>Enter the six-digit code sent to your email address.</p><form method="POST" action="{{ route('order-tracking.verify') }}">@csrf<label>Verification code<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label><button class="fd-primary">Verify &rarr;</button></form><form method="POST" action="{{ route('order-tracking.resend') }}">@csrf<button class="fd-link">Resend code</button></form>
@else
<p>Enter your order number and email to request a verification code.</p><form method="POST" action="{{ route('order-tracking.request') }}">@csrf<label>Order number<input name="order_number" value="{{ old('order_number') }}" placeholder="e.g. JW12345678" required maxlength="100"></label><label>Email address<input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required></label><button class="fd-primary" @disabled($websiteDesignPreview ?? false)>Send verification code</button></form>
@endif</section>@php($trackingMedia=app(\App\Services\WebsiteThemeCustomization::class)->media('food','tracking',asset('images/themes/food/tracking.png')))
@if(!$trackingMedia['hidden'])<aside class="fd-tracking-scene">@include('storefront.partials.theme-media',['media'=>$trackingMedia])</aside>@endif</main>@endif<x-storefront.footer/></div></x-layouts.app>
