<?php

namespace App\Services;

use App\Models\StoreSetting;

class PaymentConfigurationService
{
    private const METHOD_LABELS = [
        'simulated_card' => 'Credit Card',
        'apple_pay' => 'Apple Pay',
        'paypal' => 'PayPal',
        'klarna' => 'Klarna (4x)',
        'crypto' => 'Crypto (USDC)',
    ];

    /** @return array<string, string> */
    public function enabledStorefrontMethods(): array
    {
        return array_filter(
            self::METHOD_LABELS,
            fn (string $label, string $method): bool => $this->isEnabled($method),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function isEnabled(string $method): bool
    {
        $document = StoreSetting::query()->where('key', 'payment_gateways')->value('value');
        $gateways = data_get($document, 'data');
        if (! is_array($gateways) || $gateways === []) return true;
        $aliases = match ($method) {
            'simulated_card' => ['stripe', 'senangpay', 'razer_merchant', 'curlec'],
            'apple_pay' => ['stripe'],
            'klarna' => ['atome'],
            default => [$method],
        };
        foreach ($gateways as $gateway) {
            if (! is_array($gateway) || ! in_array($gateway['id'] ?? null, $aliases, true) || ! ($gateway['isEnabled'] ?? false)) continue;
            if ($method === 'apple_pay' && ! data_get($gateway, 'features.supportsApplePay', false)) continue;
            return true;
        }
        return false;
    }
}
