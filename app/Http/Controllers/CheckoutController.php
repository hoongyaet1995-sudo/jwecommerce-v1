<?php

namespace App\Http\Controllers;

use App\Domain\Payments\CustomerPaymentProofViewModelFactory;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartPricingService;
use App\Services\CheckoutService;
use App\Services\PaymentAttemptService;
use App\Services\PaymentConfigurationService;
use App\Services\Payments\HostedPaymentGatewayManager;
use App\Services\ShippingConfigurationService;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Http\JsonResponse;
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
    ): View|RedirectResponse {
        return $this->checkoutView($request, $pricing, $payments, $shipping, $currency);
    }

    public function paymentReturn(
        Request $request,
        OrderPayment $payment,
        CartPricingService $pricing,
        PaymentConfigurationService $payments,
        ShippingConfigurationService $shipping,
        StorefrontCurrencyContext $currency,
        HostedPaymentGatewayManager $hostedPayments,
    ): View|RedirectResponse {
        abort_unless((int) $request->session()->get('pending_payment_id') === $payment->id, 403);
        abort_unless(in_array($payment->gateway, ['stripe', 'paypal', 'billplz'], true), 404);

        $requestedState = (string) $request->query('state', 'pending');
        $state = in_array($requestedState, ['redirecting', 'cancelled', 'failed', 'pending'], true)
            ? $requestedState
            : 'pending';

        if ($payment->gateway === 'paypal' && $state === 'pending' && $request->query('token') === $payment->reference) {
            try {
                $hostedPayments->captureApprovedPayPalOrder($payment);
            } catch (\RuntimeException) {
                $payment->update(['status' => 'failed']);

                return redirect()->route('checkout.payment-return', ['payment' => $payment, 'state' => 'failed']);
            }
        }

        return $this->checkoutView($request, $pricing, $payments, $shipping, $currency, [
            'state' => $state,
            'provider' => $this->providerLabel($payment->gateway),
            'gateway' => $payment->gateway,
            'payment' => $payment,
            'reference' => $payment->order?->number ?? $payment->reference,
        ]);
    }

    public function retryHostedPayment(Request $request, OrderPayment $payment, PaymentAttemptService $attempts, HostedPaymentGatewayManager $hostedPayments): RedirectResponse
    {
        abort_unless((int) $request->session()->get('pending_payment_id') === $payment->id, 403);

        $retry = $attempts->retryHostedPayment($payment);
        $request->session()->put('pending_payment_id', $retry->id);

        try {
            return redirect()->away($hostedPayments->begin($retry, $retry->order()->firstOrFail()));
        } catch (\RuntimeException) {
            $retry->update(['status' => 'failed']);

            return redirect()->route('checkout.payment-return', ['payment' => $retry, 'state' => 'failed']);
        }
    }

    private function checkoutView(
        Request $request,
        CartPricingService $pricing,
        PaymentConfigurationService $payments,
        ShippingConfigurationService $shipping,
        StorefrontCurrencyContext $currency,
        ?array $hostedReturn = null,
    ): View|RedirectResponse {
        $items = $this->items($request);
        if ($items->isEmpty()) {
            return redirect()->route('storefront.index');
        }
        $coupon = Coupon::query()->currentlyValid()->where('code', $request->session()->get('coupon_code'))->first();
        $customerUser = $request->user('customer');
        $customer = $customerUser?->customer;
        $savedAddresses = $customer
            ? $customer->addresses()
                ->where('type', 'shipping')
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->map(fn ($address): array => [
                    'id' => (string) $address->id,
                    'label' => $address->label,
                    'isDefaultShipping' => $address->is_default,
                    'firstName' => $address->first_name,
                    'lastName' => $address->last_name,
                    'email' => $customerUser->email,
                    'phone' => $address->phone,
                    'street' => $address->line_1,
                    'apartment' => $address->line_2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postalCode' => $address->postal_code,
                    'country' => $address->country,
                ])
                ->values()
                ->all()
            : [];

        $defaultAddress = collect($savedAddresses)->firstWhere('isDefaultShipping', true) ?: ($savedAddresses[0] ?? null);
        $shippingAddress = $defaultAddress ? [
            'country' => $this->normalizeCountryCode((string) ($defaultAddress['country'] ?? 'MY')),
            'state' => $defaultAddress['state'] ?? '',
            'postal_code' => $defaultAddress['postalCode'] ?? '',
        ] : null;
        $deliveryOptions = $shipping->checkoutOptions($items, $coupon, $shippingAddress);
        $selectedDelivery = (string) old('delivery', array_key_first($deliveryOptions) ?? 'standard');
        $summary = $pricing->summarize($items, $coupon, $selectedDelivery, $shippingAddress);
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
            'deliveryOptions' => $deliveryOptions,
            'selectedDelivery' => $selectedDelivery,
            'shippingEstimateAvailable' => $deliveryOptions !== [],
            'checkoutEmail' => $customerUser?->email,
            'savedAddresses' => $savedAddresses,
            'hostedReturn' => $hostedReturn,
        ]);
    }

    public function shippingQuote(Request $request, CartPricingService $pricing, ShippingConfigurationService $shipping): JsonResponse
    {
        $items = $this->items($request);
        if ($items->isEmpty()) {
            return response()->json(['data' => ['options' => [], 'summary' => null, 'html' => '']], 422);
        }

        $data = $request->validate([
            'country' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:50'],
            'delivery' => ['nullable', 'string', 'max:100'],
        ]);
        $address = [
            'country' => $this->normalizeCountryCode((string) $data['country']),
            'state' => $data['state'] ?? '',
            'postal_code' => $data['postal_code'],
        ];
        $coupon = Coupon::query()->currentlyValid()->where('code', $request->session()->get('coupon_code'))->first();
        $options = $shipping->checkoutOptions($items, $coupon, $address);
        $selected = isset($options[$data['delivery'] ?? ''])
            ? (string) $data['delivery']
            : (string) (array_key_first($options) ?? '');
        $summary = $selected !== ''
            ? $pricing->summarize($items, $coupon, $selected, $address)
            : null;

        return response()->json(['data' => [
            'options' => array_values($options),
            'summary' => $summary,
            'html' => view('components.storefront.delivery-options', [
                'summary' => $summary ?? $pricing->summarize($items, $coupon),
                'options' => $options,
                'selected' => $selected,
                'quoteUrl' => route('checkout.shipping-quote'),
            ])->render(),
        ]]);
    }

    private function providerLabel(string $gateway): string
    {
        return match ($gateway) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'billplz' => 'Billplz',
            default => $gateway,
        };
    }

    public function store(Request $request, CheckoutService $checkout, StorefrontCurrencyContext $currency, HostedPaymentGatewayManager $hostedPayments): RedirectResponse
    {
        $request->merge([
            'country' => $this->normalizeCountryCode((string) $request->input('country', '')),
        ]);

        $data = $request->validate(['email' => ['required', 'email'], 'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'line_1' => ['required', 'string', 'max:255'], 'line_2' => ['nullable', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'], 'state' => ['nullable', 'string', 'max:255'], 'postal_code' => ['required', 'string', 'max:50'], 'country' => ['required', 'string', 'size:2'], 'delivery' => ['required', 'string', 'max:100'], 'payment_method' => ['required', 'string', 'max:50'], 'bank_account_id' => ['nullable', 'string', 'max:100'], 'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]);
        $items = $this->items($request);
        if ($items->isEmpty()) {
            return redirect()->route('storefront.index');
        }
        $coupon = Coupon::query()->currentlyValid()->where('code', $request->session()->get('coupon_code'))->first();
        if ($customerUser = $request->user('customer')) {
            $data['email'] = $customerUser->email;
        }
        $order = $checkout->checkout($data['email'], $items, $coupon, collect($data)->except(['email', 'payment_method', 'delivery', 'bank_account_id', 'payment_proof'])->all(), $data['payment_method'], $data['delivery'], $currency->snapshot(), $request->file('payment_proof'), $data['bank_account_id'] ?? null);
        if (in_array($data['payment_method'], ['stripe', 'paypal', 'billplz'], true)) {
            $payment = $order->payments()->latest('id')->firstOrFail();
            $request->session()->put('pending_payment_id', $payment->id);

            try {
                return redirect()->away($hostedPayments->begin($payment, $order));
            } catch (\RuntimeException) {
                $payment->update(['status' => 'failed']);

                return redirect()->route('checkout.payment-return', ['payment' => $payment, 'state' => 'failed']);
            }
        }
        $request->session()->forget(['cart', 'coupon_code']);
        $request->session()->put('last_order_id', $order->id);

        return redirect()->route('checkout.success');
    }

    public function success(Request $request, CustomerPaymentProofViewModelFactory $proofViewModels): View|RedirectResponse
    {
        $order = Order::query()->with(['items', 'addresses', 'payments', 'taxLines'])->find($request->session()->get('last_order_id'));
        if (! $order) {
            return redirect()->route('storefront.index');
        }

        return view('storefront.success', [
            'order' => $order,
            'paymentProof' => $proofViewModels->for($order, $order->payments->where('gateway', 'bank_transfer')->sortByDesc('id')->first()),
            'taxLine' => $order->taxLines->sortBy('id')->first(),
            'recommendations' => Product::query()
                ->where('status', 'active')
                ->whereNotIn('id', ProductVariant::query()->whereIn('id', $order->items->pluck('product_variant_id')->filter())->pluck('product_id'))
                ->with(['images', 'variants'])
                ->orderBy('title')
                ->take(3)
                ->get(),
        ]);
    }

    private function normalizeCountryCode(string $country): string
    {
        $country = strtoupper(trim($country));

        return $country === 'MALAYSIA' ? 'MY' : $country;
    }
}
