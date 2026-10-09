<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentInformationService;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsPaymentInformationController extends Controller
{
    public function __invoke(Request $request, PaymentInformationService $information, StorefrontCurrencyContext $currency): JsonResponse
    {
        $validated = $request->validate(['currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/']]);
        return response()->json($information->facts($validated['currency'] ?? $currency->current()['code'], true));
    }
}
