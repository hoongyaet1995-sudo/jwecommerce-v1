<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class ProductPurchaseSettings
{
    public const MODES = ['buy', 'quote', 'display'];
    public const PRICE_DISPLAYS = ['exact', 'from', 'hide'];

    public function validateForSave(Product $product): void
    {
        if (! in_array($product->purchase_mode, self::MODES, true)) {
            throw ValidationException::withMessages(['purchaseMode'=>'Choose a valid purchase method.']);
        }
        if (! in_array($product->price_display, self::PRICE_DISPLAYS, true)) {
            throw ValidationException::withMessages(['priceDisplay'=>'Choose a valid price display.']);
        }
        if ($product->purchase_mode !== 'buy' && $product->status !== 'draft' && !config('product_enquiries.enabled')) {
            throw ValidationException::withMessages(['status'=>'Quote and display-only products must stay in Draft until storefront support is connected.']);
        }
    }
}
