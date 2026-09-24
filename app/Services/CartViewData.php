<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartViewData
{
    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly ShippingConfigurationService $shipping,
    )
    {
    }

    /**
     * @return array{items: \Illuminate\Support\Collection<int, array{variant: ProductVariant, quantity: int}>, coupon: ?Coupon, summary: array{subtotal: int, discount: int, shipping: int, tax: int, total: int}, shippingEstimateAvailable: bool, shippingOption: ?array, shippingProgressThreshold: ?int, addons: \Illuminate\Support\Collection<int, Product>}
     */
    public function for(Request $request): array
    {
        $quantities = $request->session()->get('cart', []);
        $variants = ProductVariant::query()
            ->with(['product.images'])
            ->whereKey(array_keys($quantities))
            ->get();
        $items = $variants->map(fn (ProductVariant $variant): array => [
            'variant' => $variant,
            'quantity' => (int) $quantities[$variant->id],
            'available' => app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant),
        ]);
        $coupon = Coupon::query()
            ->currentlyValid()
            ->where('code', $request->session()->get('coupon_code'))
            ->first();

        $deliveryOptions = $this->shipping->checkoutOptions($items, $coupon);
        $shippingOption = count($deliveryOptions) === 1 ? reset($deliveryOptions) : null;
        $selectedDelivery = $shippingOption['id'] ?? 'standard';

        return [
            'items' => $items,
            'coupon' => $coupon,
            'summary' => $this->pricing->summarize($items, $coupon, $selectedDelivery),
            'shippingEstimateAvailable' => $shippingOption !== null,
            'shippingOption' => $shippingOption,
            'shippingProgressThreshold' => $this->shipping->cartFreeShippingThreshold(),
            'addons' => Product::query()
                ->where('status', 'active')
                ->whereNotIn('id', $variants->pluck('product_id'))
                ->with(['images', 'variants'])
                ->orderBy('title')
                ->take(2)
                ->get(),
        ];
    }
}
