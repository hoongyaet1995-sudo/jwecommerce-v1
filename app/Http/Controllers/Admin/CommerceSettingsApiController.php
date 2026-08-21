<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommerceSettingsApiController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->document()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['shippingRates' => ['required', 'array', 'max:100'], 'paymentGateways' => ['required', 'array', 'max:30'], 'globalPayment' => ['required', 'array'], 'bankTransfer' => ['nullable', 'array'], 'codConfig' => ['nullable', 'array']]);
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
        return response()->json(['data' => $this->document()]);
    }

    private function document(): array
    {
        $settings = StoreSetting::query()->whereIn('key', ['shipping_rates', 'payment_gateways', 'global_payment', 'bank_transfer', 'cod_config'])->pluck('value', 'key');
        return ['shippingRates' => data_get($settings->get('shipping_rates'), 'data', []), 'paymentGateways' => data_get($settings->get('payment_gateways'), 'data', []), 'globalPayment' => data_get($settings->get('global_payment'), 'data', []), 'bankTransfer' => data_get($settings->get('bank_transfer'), 'data', []), 'codConfig' => data_get($settings->get('cod_config'), 'data', [])];
    }

    private function put(string $key, array $data): void
    {
        StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => ['data' => $data]]);
    }
}
