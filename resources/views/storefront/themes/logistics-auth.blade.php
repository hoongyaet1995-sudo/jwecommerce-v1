@php

 $preview = (bool) ($websiteDesignPreview ?? false);

 $register=$mode==='register'; $forgot=$mode==='forgot'; $reset=$mode==='reset';

 $heading=$register?'Create your account':($forgot?'Forgot your password?':($reset?'Choose a new password':'Welcome back'));

 $action=$register?'customer.register.store':($forgot?'customer.password.email':($reset?'customer.password.update':'customer.login.store'));

@endphp

<x-layouts.app :title="$heading.' | '.$shopBrandName">

<div class="lg-theme" data-logistics-auth="{{ $mode }}">

<x-storefront.nav active="account" />

<x-storefront.content-frame><main class="lg-auth">

@php($themeMedia=app(\App\Services\WebsiteThemeCustomization::class)->media('logistics',$mode,asset('images/themes/logistics/account.png')))
@if(!$themeMedia['hidden'])
<aside class="lg-auth-scene">@include('storefront.partials.theme-media',['media'=>$themeMedia])</aside>
@endif

<section class="lg-auth-panel">

@if($forgot || $reset)<a class="lg-back" href="{{ route('customer.login') }}">&larr; Back to sign in</a>@endif

<h1>{{ $heading }}</h1><p class="lg-muted">{{ $register ? 'Manage your orders and container enquiries in one place.' : ($forgot ? 'Enter your email address to request password reset instructions.' : ($reset ? 'Use at least 8 characters for your new password.' : 'Sign in to manage your orders, quotations and account details.')) }}</p>



@if($errors->any())<div role="alert" class="lg-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

@if(session('status'))<p role="status" class="lg-notice">{{ session('status') }}</p>@endif

<form method="POST" action="{{ route($action) }}">@csrf

<fieldset @disabled($preview)>

@if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif

@if($register)<label>Full name<input name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="255"></label>@endif

<label>Email address<input type="email" name="email" value="{{ old('email', $email ?? '') }}" placeholder="name@youremail.com" autocomplete="email" required></label>

@if(!$forgot)

<label x-data="{visible:false}">Password<span class="lg-password"><input :type="visible ? 'text' : 'password'" type="password" name="password" autocomplete="{{ $register || $reset ? 'new-password' : 'current-password' }}" required @if($register || $reset) minlength="8" @endif><button type="button" @click="visible=!visible" :aria-label="visible ? 'Hide password' : 'Show password'"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></span>@if($register)<small>At least 8 characters</small>@endif</label>

@endif

@if($register || $reset)<label x-data="{visible:false}">Confirm password<span class="lg-password"><input :type="visible ? 'text' : 'password'" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8"><button type="button" @click="visible=!visible" :aria-label="visible ? 'Hide confirmation password' : 'Show confirmation password'"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>@endif

@if($register)<label class="lg-check"><input type="checkbox" name="terms" value="1" required> <span>I agree to the <a href="{{ url('/page/terms-and-conditions') }}">Terms &amp; Conditions</a> and <a href="{{ url('/page/privacy-policy') }}">Privacy Policy</a>.</span></label>@elseif(!$forgot && !$reset)<div class="lg-form-options"><label class="lg-check"><input type="checkbox" name="remember" value="1">Remember me</label><a href="{{ route('customer.password.request') }}">Forgot password?</a></div>@endif

<button class="lg-primary" type="submit">{{ $register ? 'Create account' : ($forgot ? 'Send reset instructions' : ($reset ? 'Reset password' : 'Sign in')) }} &rarr;</button>

</fieldset></form>

@if($forgot)<p class="lg-fine">If an account matches this email, you will receive reset instructions.</p>@elseif(!$reset)<p class="lg-auth-switch">{{ $register ? 'Already have an account?' : 'New here?' }} <a href="{{ route($register ? 'customer.login' : 'customer.register') }}">{{ $register ? 'Sign in' : 'Create an account' }}</a></p>@endif

</section></main></x-storefront.content-frame><x-storefront.footer />

</div></x-layouts.app>
