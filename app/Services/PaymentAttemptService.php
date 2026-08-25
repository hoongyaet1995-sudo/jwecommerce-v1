<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentAttemptService
{
    /** @param array<string, mixed> $method */
    public function createPendingPayment(Order $order, array $method, string $currency, ?string $bankAccountId = null): OrderPayment
    {
        $gateway = (string) $method['id'];
        $snapshot = $this->snapshotFor($method, $bankAccountId);
        $expiresAt = $gateway === 'bank_transfer'
            ? now()->addHours((int) ($method['bankTransfer']['paymentWindowHours'] ?? 24))
            : null;

        return OrderPayment::query()->create([
            'order_id' => $order->id,
            'gateway' => $gateway,
            'provider' => $gateway,
            'environment' => in_array($gateway, ['bank_transfer', 'cod'], true)
                ? 'offline'
                : ((bool) data_get($method, 'gateway.isSandbox', false) ? 'sandbox' : 'live'),
            'status' => 'pending',
            'reference' => strtoupper(str_replace('_', '-', $gateway)).'-'.$order->number,
            'idempotency_key' => (string) Str::uuid(),
            'amount' => $order->total,
            'currency' => $currency,
            'configuration_snapshot' => $snapshot,
            'expires_at' => $expiresAt,
        ]);
    }

    /** @param array<string, mixed> $method @return array<string, mixed> */
    private function snapshotFor(array $method, ?string $bankAccountId): array
    {
        if ($method['id'] === 'bank_transfer') {
            $configuration = $method['bankTransfer'];
            $accounts = $configuration['bankAccounts'] ?? [];
            $account = $bankAccountId
                ? collect($accounts)->firstWhere('id', $bankAccountId)
                : (collect($accounts)->firstWhere('isPrimary', true) ?? ($accounts[0] ?? null));
            if (! $account) {
                throw ValidationException::withMessages(['bank_account_id' => 'The selected bank account is not available.']);
            }

            return [
                'bankAccount' => $account,
                'allowProofUpload' => (bool) ($configuration['allowProofUpload'] ?? false),
                'paymentWindowHours' => (int) ($configuration['paymentWindowHours'] ?? 24),
                'verificationInstructions' => $configuration['verificationInstructions'] ?? [],
                'quietDisclosure' => $configuration['quietDisclosure'] ?? null,
            ];
        }

        if ($method['id'] === 'cod') {
            return ['cod' => $method['cod'] ?? []];
        }

        return [
            'gateway' => collect($method['gateway'] ?? [])
                ->except(['credentials', 'credentialStatus'])
                ->all(),
        ];
    }
}
