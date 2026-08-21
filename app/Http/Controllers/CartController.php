<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Coupon;
use App\Services\CartViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Request $request, CartViewData $cartViewData): View
    {
        return view('storefront.cart', $cartViewData->for($request));
    }

    public function drawer(Request $request, CartViewData $cartViewData): View
    {
        return view('storefront.partials.cart-drawer', $cartViewData->for($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'exists:product_variants,id'], 'quantity' => ['required', 'integer', 'min:1'], 'checkout' => ['nullable', 'boolean']]);
        $cart = $request->session()->get('cart', []); $cart[$data['variant_id']] = ($cart[$data['variant_id']] ?? 0) + $data['quantity'];
        $request->session()->put('cart', $cart);
        return redirect()->route($request->boolean('checkout') ? 'checkout.create' : 'cart.show');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse { $request->validate(['quantity' => ['required', 'integer', 'min:1']]); $cart = $request->session()->get('cart', []); $cart[$variant->id] = $request->integer('quantity'); $request->session()->put('cart', $cart); return redirect()->route('cart.show'); }
    public function destroy(Request $request, ProductVariant $variant): RedirectResponse { $cart = $request->session()->get('cart', []); unset($cart[$variant->id]); $request->session()->put('cart', $cart); return redirect()->route('cart.show'); }
    public function applyCoupon(Request $request): RedirectResponse { $data = $request->validate(['code' => ['required','string']]); $coupon = Coupon::query()->currentlyValid()->whereRaw('lower(code) = ?', [strtolower($data['code'])])->first(); if ($coupon) $request->session()->put('coupon_code', $coupon->code); else $request->session()->flash('coupon_error', 'Coupon code is not valid.'); return redirect()->route('cart.show'); }
    public function removeCoupon(Request $request): RedirectResponse { $request->session()->forget('coupon_code'); return redirect()->route('cart.show'); }
}
