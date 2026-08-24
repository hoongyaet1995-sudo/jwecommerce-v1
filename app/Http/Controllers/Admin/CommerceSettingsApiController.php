<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Support\CurrencySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommerceSettingsApiController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->document()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shippingRates' => ['required', 'array', 'max:100'],
            'paymentGateways' => ['required', 'array', 'max:30'],
            'globalPayment' => ['required', 'array'],
            'bankTransfer' => ['nullable', 'array'],
            'codConfig' => ['nullable', 'array'],
            'currencies' => ['nullable', 'array', 'min:1', 'max:50'],
            'currencies.*.id' => ['required', 'string', 'max:20'],
            'currencies.*.code' => ['required', 'string', 'size:3'],
            'currencies.*.title' => ['required', 'string', 'max:120'],
            'currencies.*.symbolLeft' => ['nullable', 'string', 'max:20'],
            'currencies.*.symbolRight' => ['nullable', 'string', 'max:20'],
            'currencies.*.decimalPlaces' => ['required', 'integer', 'min:0', 'max:4'],
            'currencies.*.exchangeRate' => ['required', 'numeric', 'gt:0'],
            'currencies.*.isDefault' => ['required', 'boolean'],
            'currencies.*.autoUpdate' => ['required', 'boolean'],
            'currencies.*.lastUpdated' => ['nullable', 'string', 'max:120'],
            'currencies.*.isEnabled' => ['required', 'boolean'],
        ]);
        $currencySettings = app(CurrencySettings::class);
        $currencies = array_key_exists('currencies', $data)
            ? $currencySettings->normalize($data['currencies'])
            : $currencySettings->all();
        $codes = collect($currencies)->pluck('code');
        $defaults = collect($currencies)->filter(fn (array $currency): bool => $currency['isDefault'] && $currency['isEnabled']);

        if ($codes->count() !== $codes->unique()->count() || $defaults->count() !== 1) {
            throw ValidationException::withMessages(['currencies' => ['Currency codes must be unique and exactly one enabled currency must be the store default.']]);
        }
        $gateways = collect($data['paymentGateways'])->map(function ($gateway): array {
            $gateway = is_array($gateway) ? $gateway : [];
            $gateway['credentials'] = [];
            return $gateway;
        })->values()->all();
        $this->put('shipping_rates', $data['shippingRates']);
        $this->put('payment_gateways', $gateways);
        $this->put('global_payment', $data['globalPayment']);
        $this->put('bank_transfer', $data['bankTransfer'] ?? []);
        $this->put('cod_config', $data['codConfig'] ?? []);
        $this->put(CurrencySettings::KEY, $currencies);
        return response()->json(['data' => $this->document()]);
    }

    private function document(): array
    {
        $settings = StoreSetting::query()->whereIn('key', ['shipping_rates', 'payment_gateways', 'global_payment', 'bank_transfer', 'cod_config'])->pluck('value', 'key');
        return ['shippingRates' => data_get($settings->get('shipping_rates'), 'data', []), 'paymentGateways' => data_get($settings->get('payment_gateways'), 'data', []), 'globalPayment' => data_get($settings->get('global_payment'), 'data', []), 'bankTransfer' => data_get($settings->get('bank_transfer'), 'data', []), 'codConfig' => data_get($settings->get('cod_config'), 'data', []), 'currencies' => app(CurrencySettings::class)->all()];
    }

    private function put(string $key, array $data): void
    {
        StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => ['data' => $data]]);
    }
}
