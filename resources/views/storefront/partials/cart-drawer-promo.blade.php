@include('storefront.partials.mix-match-progress', ['mixCompact' => true])
<details class="cart-drawer-promo" @if(session('coupon_error')) open @endif>
    <summary><span>Have a promo code?</span><svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg></summary>
    <form method="POST" action="{{ route('cart.coupon.apply') }}" data-cart-drawer-form>
        @csrf
        <input name="code" aria-label="Promo code" placeholder="Enter promo code" autocomplete="off" required>
        <button type="submit">Apply</button>
    </form>
    @if(session('coupon_error'))<p class="cart-drawer-promo-error" role="alert">{{ session('coupon_error') }}</p>@endif
</details>
