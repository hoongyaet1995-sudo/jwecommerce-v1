<?php

namespace App\Services\Support;

use App\Services\SupportFaqService;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** A short-lived copy of server-rendered totals, never browser-supplied payment amounts. */
class SupportCheckoutSnapshot
{
    private function signature(Request $request, Collection $items): string
    {
        return hash('sha256', json_encode(['items' => $items->map(fn ($i) => [(int) $i['variant']->id, (int) $i['quantity'], (int) $i['variant']->price])->sortBy(fn ($i) => $i[0])->values()->all(), 'coupon' => $request->session()->get('coupon_code'), 'currency' => (new StorefrontCurrencyContext(app(\App\Support\CurrencySettings::class), $request))->current()['code']]));
    }

    public function capture(Request $request, Collection $items, ?array $summary, string $surface, string $delivery, bool $shippingConfirmed): void
    {
        $scope = app(SupportPageContext::class)->settings(app(SupportFaqService::class)->read());
        if (! $scope['enabled'] || ! $scope['cart']) {
            return;
        }
        if (! $summary) {
            $request->session()->forget('support_checkout_context.'.$surface);

            return;
        }
        $request->session()->put('support_checkout_context.'.$surface, ['signature' => $this->signature($request, $items), 'surface' => $surface, 'createdAt' => now()->timestamp, 'delivery' => $delivery, 'shippingEstimateAvailable' => $shippingConfirmed, 'summary' => array_intersect_key($summary, array_flip(['subtotal', 'discount', 'shipping', 'payment_surcharge', 'tax', 'total']))]);
    }

    public function read(Request $request, Collection $items, string $surface): ?array
    {
        $state = $request->session()->get('support_checkout_context.'.$surface);
        if (! is_array($state) || ($state['surface'] ?? null) !== $surface || ($state['createdAt'] ?? 0) < now()->subMinutes(5)->timestamp || ! hash_equals($state['signature'] ?? '', $this->signature($request, $items))) {
            return null;
        }

        return array_intersect_key($state, array_flip(['createdAt', 'delivery', 'shippingEstimateAvailable', 'summary'])) + ['limitation' => 'Latest server-rendered checkout estimate, not a guaranteed final charge. Reconfirm on the checkout form; no payment has been made by AI.'];
    }
}
