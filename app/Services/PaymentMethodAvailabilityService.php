<?php

namespace App\Services;

use App\Models\PaymentGatewayCredential;
use App\Models\StoreSetting;

class PaymentMethodAvailabilityService
{
    /**
     * @param array{currency: string, amount: int, address?: array<string, mixed>} $context
     * @return array<string, array<string, mixed>>
     */
    public function checkoutMethods(array $context): array
    {
        $currency = strtoupper((string) ($context['currency'] ?? ''));
        $amount = max(0, (int) ($context['amount'] ?? 0));
        $address = is_array($context['address'] ?? null) ? $context['address'] : [];
        $methods = [];

        foreach ($this->onlineGateways() as $gateway) {
            if (! $this->onlineGatewayIsEligible($gateway, $currency, $amount)) {
                continue;
            }

            $id = (string) $gateway['id'];
            $methods[$id] = [
                'id' => $id,
                'label' => trim((string) ($gateway['name'] ?? '')) ?: $this->labelFor($id),
                'kind' => 'hosted',
                'gateway' => $gateway,
            ];
        }

        $bankTransfer = $this->bankTransferConfiguration();
        if (($bankTransfer['isEnabled'] ?? false) && ($bankTransfer['bankAccounts'] ?? []) !== []) {
            $methods['bank_transfer'] = [
                'id' => 'bank_transfer',
                'label' => 'Bank / DuitNow',
                'kind' => 'offline',
                'bankTransfer' => $bankTransfer,
            ];
        }

        $cod = $this->codConfiguration();
        if ($this->codIsEligible($cod, $amount, $address)) {
            $methods['cod'] = [
                'id' => 'cod',
                'label' => 'Cash on Delivery',
                'kind' => 'offline',
                'cod' => $cod + ['surchargeCents' => $this->codSurcharge($cod, $amount)],
            ];
        }

        return $methods;
    }

    /** @param array<string, mixed> $cod */
    public function codSurcharge(array $cod, int $amount): int
    {
        $surcharge = max(0, (float) ($cod['surchargeAmount'] ?? 0));

        return ($cod['surchargeType'] ?? 'fixed') === 'percentage'
            ? (int) round($amount * min(100, $surcharge) / 100)
            : (int) round($surcharge * 100);
    }

    /** @return array<string, mixed> */
    public function bankTransferConfiguration(): array
    {
        $data = data_get(StoreSetting::query()->where('key', 'bank_transfer')->value('value'), 'data', []);
        $data = is_array($data) ? $data : [];
        $accounts = collect($data['bankAccounts'] ?? [])
            ->filter(fn ($account): bool => is_array($account) && ($account['isActive'] ?? false))
            ->values()
            ->all();

        return [
            'isEnabled' => (bool) ($data['isEnabled'] ?? false),
            'allowProofUpload' => array_key_exists('allowProofUpload', $data)
                ? (bool) $data['allowProofUpload']
                : (bool) ($data['requireProofUpload'] ?? false),
            'paymentWindowHours' => max(1, (int) ($data['paymentWindowHours'] ?? max(1, (int) ($data['paymentWindowMinutes'] ?? 1440) / 60))),
            'verificationInstructions' => $this->instructions($data['verificationInstructions'] ?? []),
            'quietDisclosure' => trim((string) ($data['quietDisclosure'] ?? 'Your order is reserved and will be confirmed after payment verification.')),
            'bankAccounts' => $accounts,
        ];
    }

    /** @return array<string, mixed> */
    public function codConfiguration(): array
    {
        $data = data_get(StoreSetting::query()->where('key', 'cod_config')->value('value'), 'data', []);

        return is_array($data) ? $data : [];
    }

    /** @return list<array<string, mixed>> */
    private function onlineGateways(): array
    {
        $gateways = data_get(StoreSetting::query()->where('key', 'payment_gateways')->value('value'), 'data', []);

        return collect(is_array($gateways) ? $gateways : [])
            ->filter(fn ($gateway): bool => is_array($gateway) && in_array($gateway['id'] ?? null, ['stripe', 'paypal', 'billplz'], true))
            ->sortBy(fn (array $gateway): int => (int) ($gateway['sortOrder'] ?? PHP_INT_MAX))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $gateway */
    private function onlineGatewayIsEligible(array $gateway, string $currency, int $amount): bool
    {
        if (! ($gateway['isEnabled'] ?? false)) {
            return false;
        }

        $id = (string) ($gateway['id'] ?? '');
        $environment = ($gateway['isSandbox'] ?? false) ? 'sandbox' : 'live';
        $credentials = PaymentGatewayCredential::forGateway($id, $environment)?->credentials ?? [];
        if (! $this->credentialsComplete($id, $credentials)) {
            return false;
        }

        $currencies = array_map('strtoupper', array_filter($gateway['supportedCurrencies'] ?? [], 'is_string'));
        if ($currencies !== [] && ! in_array($currency, $currencies, true)) {
            return false;
        }

        $minimum = (int) round(max(0, (float) ($gateway['limits']['minAmount'] ?? 0)) * 100);
        $maximum = (int) round(max(0, (float) ($gateway['limits']['maxAmount'] ?? 0)) * 100);

        return $amount >= $minimum && ($maximum === 0 || $amount <= $maximum);
    }

    /** @param array<string, mixed> $cod @param array<string, mixed> $address */
    private function codIsEligible(array $cod, int $amount, array $address): bool
    {
        if (! ($cod['isEnabled'] ?? false)) {
            return false;
        }

        $minimum = (int) round(max(0, (float) ($cod['minOrderAmount'] ?? 0)) * 100);
        $maximum = (int) round(max(0, (float) ($cod['maxOrderAmount'] ?? 0)) * 100);
        if ($amount < $minimum || ($maximum > 0 && $amount > $maximum)) {
            return false;
        }

        $country = strtoupper((string) ($address['country'] ?? 'MY'));
        $state = strtolower((string) ($address['state'] ?? ''));
        $postCode = trim((string) ($address['postal_code'] ?? ''));
        $eastMalaysia = str_contains($state, 'sabah') || str_contains($state, 'sarawak');

        return match ($cod['allowedRegions'] ?? 'all') {
            'all' => true,
            'peninsular_only' => $country === 'MY' && ! $eastMalaysia,
            'east_malaysia_only' => $country === 'MY' && $eastMalaysia,
            'custom_postcodes' => in_array($postCode, $cod['customPostcodes'] ?? [], true),
            default => false,
        };
    }

    /** @param array<string, mixed> $credentials */
    private function credentialsComplete(string $gatewayId, array $credentials): bool
    {
        foreach ($this->requiredCredentialKeys($gatewayId) as $key) {
            if (blank($credentials[$key] ?? null)) {
                return false;
            }
        }

        return true;
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

    /** @return list<string> */
    private function instructions(mixed $instructions): array
    {
        if (is_array($instructions)) {
            return array_values(array_filter(array_map('strval', $instructions), fn (string $line): bool => trim($line) !== ''));
        }

        return array_values(array_filter(preg_split('/\r?\n/', (string) $instructions) ?: [], fn (string $line): bool => trim($line) !== ''));
    }

    private function labelFor(string $gatewayId): string
    {
        return match ($gatewayId) {
            'stripe' => 'Credit Card',
            'paypal' => 'PayPal',
            'billplz' => 'Billplz',
            default => $gatewayId,
        };
    }
}
