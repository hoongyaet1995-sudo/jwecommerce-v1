<?php

namespace App\Http\Controllers;

use App\Domain\Orders\OrderTrackingPayloadFactory;
use App\Models\OrderTrackingVerification;
use App\Services\OrderTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackingController extends Controller
{
    public function preview(Request $request, OrderTrackingPayloadFactory $payloads): View
    {
        $step = $request->query('step', 'lookup');
        $trackingPayload = null;

        if ($step === 'verified_result') {
            $attempt = OrderTrackingVerification::query()
                ->with('order')
                ->where('public_id', $request->session()->get('order_tracking.verified_attempt_id'))
                ->whereNotNull('verified_at')
                ->first();

            if (! $attempt?->order) {
                $step = 'lookup';
            } else {
                $trackingPayload = $payloads->for($attempt->order);
            }
        }

        return view(match(app(\App\Services\WebsiteDesignService::class)->surface()) { 'logistics'=>'storefront.themes.logistics-tracking', 'food'=>'storefront.themes.food-tracking', 'home_deco'=>'storefront.themes.home-deco-tracking', 'beauty'=>'storefront.themes.beauty-tracking', 'universal'=>'storefront.themes.universal-tracking', 'fashion'=>'storefront.themes.fashion-tracking', 'wedding'=>'storefront.themes.wedding-tracking', 'jewelry'=>'storefront.themes.jewelry-tracking', 'baby'=>'storefront.themes.baby-tracking', 'tech'=>'storefront.themes.tech-tracking', default=>'storefront.order-tracking' }, [
            'step' => $step,
            'submittedEmail' => $request->session()->get('order_tracking.email'),
            'trackingPayload' => $trackingPayload,
        ]);
    }

    public function requestCode(Request $request, OrderTrackingService $tracking): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $attempt = $tracking->request($data['order_number'], $data['email']);

        $request->session()->put('order_tracking.email', $data['email']);
        $request->session()->put('order_tracking.order_number', $data['order_number']);
        $request->session()->put('order_tracking.attempt_id', $attempt?->public_id);

        return redirect()->route('order-tracking.show', ['step' => 'verify']);
    }

    public function verify(Request $request, OrderTrackingService $tracking): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $attempt = $tracking->verify(
            $request->session()->get('order_tracking.attempt_id'),
            $data['code'],
        );

        if (! $attempt) {
            return redirect()
                ->route('order-tracking.show', ['step' => 'verify'])
                ->with('order_tracking.error', 'We couldn’t verify those details. Please check your order number and email, or request a new code.');
        }

        $request->session()->put('order_tracking.verified_attempt_id', $attempt->public_id);

        return redirect()->route('order-tracking.show', ['step' => 'verified_result']);
    }

    public function resend(Request $request, OrderTrackingService $tracking): RedirectResponse
    {
        $email = $request->session()->get('order_tracking.email');
        $number = $request->session()->get('order_tracking.order_number');

        $attempt = is_string($email) && is_string($number)
            ? $tracking->request($number, $email)
            : null;

        $request->session()->put('order_tracking.attempt_id', $attempt?->public_id);

        return redirect()->route('order-tracking.show', ['step' => 'verify']);
    }
}
