<?php

namespace App\Http\Controllers;

use App\Support\CurrencySettings;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class StorefrontCurrencyController extends Controller
{
    public function __invoke(Request $request, CurrencySettings $currencies): Response
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
        ]);
        $code = strtoupper(trim($data['currency']));

        $isEnabled = collect($currencies->enabled())->contains(
            static fn (array $currency): bool => $currency['code'] === $code,
        );

        if (! $isEnabled) {
            throw ValidationException::withMessages([
                'currency' => 'The selected currency is unavailable.',
            ]);
        }

        $request->session()->put(StorefrontCurrencyContext::SESSION_KEY, $code);

        return response()->noContent();
    }
}
