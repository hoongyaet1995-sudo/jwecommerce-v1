<?php

namespace App\Services;

use App\Models\StoreSetting;

final class PaymentInformationService
{
    public function __construct(private readonly PaymentMethodAvailabilityService $availability) {}

    public function facts(string $currency, bool $includeSetup = false): array
    {
        $data = data_get(StoreSetting::query()->where('key', 'payment_gateways')->value('value'), 'data', []);
        $methods = [];
        foreach (collect(is_array($data) ? $data : [])->filter(fn ($g) => is_array($g) && ($g['isEnabled'] ?? false))->sortBy('sortOrder') as $gateway) {
            $status = $this->availability->informationGatewayStatus($gateway, $currency);
            if (! $includeSetup && $status !== 'available') continue;
            $category = (string) ($gateway['category'] ?? 'card');
            $methods[] = ['id' => (string) ($gateway['id'] ?? ''), 'title' => (string) ($gateway['name'] ?? $gateway['id'] ?? 'Payment'), 'category' => $category, 'status' => $status,
                'description' => match ($category) { 'bnpl' => 'Review instalment options and provider terms at checkout.', 'online_banking_fpx', 'bank' => 'Follow the payment instructions provided at checkout.', default => 'Choose this option at checkout and follow the provider instructions.' }];
        }
        $bank = $this->availability->bankTransferConfiguration();
        $bankAvailable = ($bank['isEnabled'] ?? false) && ($bank['bankAccounts'] ?? []) !== [];
        if ($bankAvailable) $methods[] = ['id' => 'bank_transfer', 'title' => 'Bank / DuitNow', 'category' => 'bank', 'status' => 'available', 'description' => 'Use the account details and payment reference provided with your order.'];
        if ($this->availability->codConfiguration()['isEnabled'] ?? false) $methods[] = ['id' => 'cod', 'title' => 'Cash on Delivery', 'category' => 'offline', 'status' => 'available', 'description' => 'Available for eligible orders and delivery areas, confirmed at checkout.'];
        return ['methods' => $methods, 'bankTransfer' => (bool) $bankAvailable];
    }
}
