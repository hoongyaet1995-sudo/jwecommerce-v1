<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, PaymentWebhookService $webhooks): JsonResponse
    {
        $result = $webhooks->receiveStripe($request);

        return response()->json(['received' => $result['received']]);
    }

    public function billplz(Request $request, PaymentWebhookService $webhooks): JsonResponse
    {
        $result = $webhooks->receiveBillplz($request);

        return response()->json(['received' => $result['received']]);
    }

    public function paypal(Request $request, PaymentWebhookService $webhooks): JsonResponse
    {
        $result = $webhooks->receivePayPal($request);

        return response()->json(['received' => $result['received']]);
    }
}
