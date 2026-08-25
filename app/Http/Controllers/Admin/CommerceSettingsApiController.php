<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewayCredential;
use App\Models\StoreSetting;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\DB;
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
        $gateways = $this->prepareGateways($data['paymentGateways']);

        DB::transaction(function () use ($data, $currencies, $gateways): void {
            foreach ($gateways as $gateway) {
                if ($gateway['submittedCredentials'] === []) {
                    continue;
                }

                PaymentGatewayCredential::query()->updateOrCreate(
                    ['gateway_id' => $gateway['id'], 'environment' => $gateway['environment']],
                    ['credentials' => $gateway['effectiveCredentials']],
                );
            }

            $this->put('shipping_rates', $data['shippingRates']);
            $this->put('payment_gateways', collect($gateways)->map(function (array $gateway): array {
                unset($gateway['submittedCredentials'], $gateway['effectiveCredentials'], $gateway['environment']);
                return $gateway;
            })->values()->all());
            $this->put('global_payment', $data['globalPayment']);
            $this->put('bank_transfer', $data['bankTransfer'] ?? []);
            $this->put('cod_config', $data['codConfig'] ?? []);
            $this->put(CurrencySettings::KEY, $currencies);
        });
        return response()->json(['data' => $this->document()]);
    }

    public function updateBankTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bankTransfer' => ['required', 'array'],
        ]);

        $this->put('bank_transfer', $data['bankTransfer']);

        return response()->json(['data' => $this->document()]);
    }

    public function updateCod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codConfig' => ['required', 'array'],
        ]);

        $this->put('cod_config', $data['codConfig']);

        return response()->json(['data' => $this->document()]);
    }

    private function document(): array
    {
        $settings = StoreSetting::query()->whereIn('key', ['shipping_rates', 'payment_gateways', 'global_payment', 'bank_transfer', 'cod_config'])->pluck('value', 'key');
        return [
            'shippingRates' => data_get($settings->get('shipping_rates'), 'data', []),
            'paymentGateways' => collect(data_get($settings->get('payment_gateways'), 'data', []))
                ->map(fn ($gateway): array => $this->publicGateway(is_array($gateway) ? $gateway : []))
                ->values()
                ->all(),
            'globalPayment' => data_get($settings->get('global_payment'), 'data', []),
            'bankTransfer' => data_get($settings->get('bank_transfer'), 'data', []),
            'codConfig' => data_get($settings->get('cod_config'), 'data', []),
            'currencies' => app(CurrencySettings::class)->all(),
        ];
    }

    /** @param array<int, mixed> $gateways @return array<int, array<string, mixed>> */
    private function prepareGateways(array $gateways): array
    {
        $errors = [];

        $prepared = collect($gateways)->map(function ($gateway, int $index) use (&$errors): array {
            $gateway = is_array($gateway) ? $gateway : [];
            $id = (string) ($gateway['id'] ?? '');
            $environment = ($gateway['isSandbox'] ?? false) ? 'sandbox' : 'live';
            $submitted = collect($gateway['credentials'] ?? [])
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->all();
            $stored = PaymentGatewayCredential::forGateway($id, $environment)?->credentials ?? [];
            $effective = array_replace($stored, $submitted);

            foreach ($this->requiredCredentialKeys($id) as $key) {
                if (($gateway['isEnabled'] ?? false) && blank($effective[$key] ?? null)) {
                    $errors["paymentGateways.$index.credentials.$key"] = ['Configure this credential before enabling the gateway.'];
                }
            }

            $gateway['credentials'] = [];
            $gateway['credentialStatus'] = $this->credentialStatus($id, $effective);
            $gateway['submittedCredentials'] = $submitted;
            $gateway['effectiveCredentials'] = $effective;
            $gateway['environment'] = $environment;

            return $gateway;
        })->values()->all();

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $prepared;
    }

    /** @return list<string> */
    private function requiredCredentialKeys(string $gatewayId): array
    {
        return match ($gatewayId) {
            'stripe' => ['publishableKey', 'secretKey', 'webhookSecret'],
            'paypal' => ['clientId', 'clientSecret'],
            'billplz' => ['apiKey', 'collectionId', 'signatureKey'],
            default => [],
        };
    }

    /** @param array<string, mixed> $gateway @return array<string, mixed> */
    private function publicGateway(array $gateway): array
    {
        $id = (string) ($gateway['id'] ?? '');
        $environment = ($gateway['isSandbox'] ?? false) ? 'sandbox' : 'live';
        $credentials = PaymentGatewayCredential::forGateway($id, $environment)?->credentials ?? [];
        $gateway['credentials'] = [];
        $gateway['credentialStatus'] = $this->credentialStatus($id, $credentials);

        return $gateway;
    }

    /** @param array<string, mixed> $credentials */
    private function credentialStatus(string $gatewayId, array $credentials): string
    {
        $required = $this->requiredCredentialKeys($gatewayId);
        if ($required === []) {
            return 'not_required';
        }

        return collect($required)->every(fn (string $key): bool => filled($credentials[$key] ?? null))
            ? 'configured'
            : 'missing';
    }

    private function put(string $key, array $data): void
    {
        StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => ['data' => $data]]);
    }
}
