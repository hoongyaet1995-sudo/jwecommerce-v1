<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\CartViewData;
use App\Services\CustomerCouponEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        $variant = ProductVariant::query()->findOrFail($data['variant_id']);

        $available = app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant);
        if (! $variant->enabled || $available === null || $available < 1) {
            throw ValidationException::withMessages(['variant_id' => 'This SKU is currently out of stock.']);
        }

        $cart = $request->session()->get('cart', []);
        $currentQuantity = (int) ($cart[$data['variant_id']] ?? 0);

        if ($currentQuantity + $data['quantity'] > $available) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds available stock.']);
        }

        $cart[$data['variant_id']] = $currentQuantity + $data['quantity'];
        $request->session()->put('cart', $cart);

        return redirect()->route($request->boolean('checkout') ? 'checkout.create' : 'cart.show');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        $available = app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant);
        if (! $variant->enabled || $available === null || $request->integer('quantity') > $available) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds available stock.']);
        }
        $cart = $request->session()->get('cart', []);
        $cart[$variant->id] = $request->integer('quantity');
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.show');
    }

    public function destroy(Request $request, ProductVariant $variant): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$variant->id]);
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.show');
    }

    public function applyCoupon(Request $request, CustomerCouponEligibilityService $coupons): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $returnTo = $this->couponReturnRoute($request);
        $result = $coupons->apply($request->user('customer')?->customer, $data['code'], $request);

        if (! $result->applied) {
            $request->session()->flash('coupon_error', $result->message);
        }

        return redirect()->to($returnTo);
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $request->session()->forget('coupon_code');

        return redirect()->to($this->couponReturnRoute($request));
    }

    private function couponReturnRoute(Request $request): string
    {
        return $request->input('return_to') === 'checkout'
            ? route('checkout.create')
            : route('cart.show');
    }
}
