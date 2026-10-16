<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** One purchase contract for every storefront theme and transaction entry. */
class ProductPurchaseRules
{
    public function isBuy(Product $product): bool
    {
        return $product->purchase_mode === 'buy';
    }

    public function showsPrice(Product $product): bool
    {
        return $this->isBuy($product) || ($product->purchase_mode === 'quote' && in_array($product->price_display, ['exact', 'from'], true));
    }

    public function price(Product $product, ?int $amount): string
    {
        if (! $this->showsPrice($product)) return '';
        return ($product->purchase_mode === 'quote' && $product->price_display === 'from' ? 'From ' : '').app(\App\Support\StorefrontCurrencyContext::class)->format($amount ?? $product->base_price);
    }

    public function assertPurchasable(ProductVariant $variant): void
    {
        $product = $variant->product;
        if (! $product || ! $this->isBuy($product) || $product->status !== 'active') {
            throw ValidationException::withMessages(['variant_id' => 'This item is no longer available for online purchase.']);
        }
    }

    public function cartQuantities(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        if (! $cart) return [];
        $valid = ProductVariant::query()->whereKey(array_keys($cart))->whereHas('product', fn ($q) => $q->where('status', 'active')->where('purchase_mode', 'buy'))->pluck('id')->all();
        $kept = array_intersect_key($cart, array_flip($valid));
        if (count($kept) !== count($cart)) {
            $request->session()->put('cart', $kept);
            $request->session()->flash('cart_purchase_notice', 'Some items are no longer available for online purchase and were removed from your cart.');
        }
        return $kept;
    }
}
