<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Coupon;
use App\Services\CartPricingService;
use App\Services\CheckoutService;
use App\Services\PaymentConfigurationService;
use App\Services\ShippingConfigurationService;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private function items(Request $request)
    {
        $quantities = $request->session()->get('cart', []);
        return ProductVariant::query()->with('product')->whereKey(array_keys($quantities))->get()->map(fn (ProductVariant $variant) => ['variant' => $variant, 'quantity' => (int) $quantities[$variant->id]]);
    }

    public function create(
        Request $request,
        CartPricingService $pricing,
        PaymentConfigurationService $payments,
        ShippingConfigurationService $shipping,
        StorefrontCurrencyContext $currency,
    ): View|RedirectResponse
    {
        $items = $this->items($request);
        if ($items->isEmpty()) return redirect()->route('storefront.index');
        $coupon = Coupon::query()->currentlyValid()->where('code', $request->session()->get('coupon_code'))->first();
        $user = $request->user();

        $summary = $pricing->summarize($items, $coupon);
        $paymentMethodModels = $payments->checkoutMethods([
            'currency' => $currency->current()['code'],
            'amount' => $summary['total'],
            'address' => [],
        ]);

        return view('storefront.checkout', [
            'items' => $items,
            'coupon' => $coupon,
            'summary' => $summary,
            'paymentMethods' => collect($paymentMethodModels)->mapWithKeys(fn (array $method, string $id): array => [$id => $method['label']])->all(),
            'paymentMethodModels' => $paymentMethodModels,
            'deliveryOptions' => $shipping->checkoutOptions($items, $coupon),
            'checkoutEmail' => $user?->customer ? $user->email : null,
        ]);
    }

    public function store(Request $request, CheckoutService $checkout, StorefrontCurrencyContext $currency): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'line_1' => ['required', 'string', 'max:255'], 'line_2' => ['nullable', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'], 'state' => ['nullable', 'string', 'max:255'], 'postal_code' => ['required', 'string', 'max:50'], 'country' => ['required', 'string', 'size:2'], 'delivery' => ['nullable', 'in:standard,express,white_glove'], 'payment_method' => ['required', 'string', 'max:50'], 'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]); $items = $this->items($request);
        if ($items->isEmpty()) return redirect()->route('storefront.index');
        $coupon = Coupon::query()->currentlyValid()->where('code', $request->session()->get('coupon_code'))->first();
        if ($request->user() && ! $request->user()->is_admin) $data['email'] = $request->user()->email;
        $order = $checkout->checkout($data['email'], $items, $coupon, collect($data)->except(['email', 'payment_method', 'delivery', 'payment_proof'])->all(), $data['payment_method'], $data['delivery'] ?? 'standard', $currency->snapshot(), $request->file('payment_proof'));
        $request->session()->forget(['cart', 'coupon_code']); $request->session()->put('last_order_id', $order->id);
        return redirect()->route('checkout.success');
    }

    public function success(Request $request): View|RedirectResponse
    {
        $order = Order::query()->with(['items', 'addresses', 'payments'])->find($request->session()->get('last_order_id'));
        if (! $order) return redirect()->route('storefront.index');
        return view('storefront.success', [
            'order' => $order,
            'recommendations' => Product::query()
                ->where('status', 'active')
                ->whereNotIn('id', ProductVariant::query()->whereIn('id', $order->items->pluck('product_variant_id')->filter())->pluck('product_id'))
                ->with(['images', 'variants'])
                ->orderBy('title')
                ->take(3)
                ->get(),
        ]);
    }
}
