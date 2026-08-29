<?php

namespace App\Http\Controllers;

use App\Services\Shipping\EasyParcelWebhookReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class EasyParcelWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, EasyParcelWebhookReceiptService $receipts): JsonResponse
    {
        $configuredToken = config('services.easyparcel.webhook_path_token');

        if (! is_string($configuredToken) || $configuredToken === '' || ! hash_equals($configuredToken, $token)) {
            abort(404);
        }

        if (! $request->isJson()) {
            abort(415);
        }

        $rawBody = $request->getContent();
        if (strlen($rawBody) > 65536) {
            abort(413);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            abort(422);
        }

        $receipts->receive($rawBody, $payload);

        return response()->json(['received' => true]);
    }
}
