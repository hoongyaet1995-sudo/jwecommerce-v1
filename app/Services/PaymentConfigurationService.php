<?php

namespace App\Services;

use App\Support\StorefrontCurrencyContext;

class PaymentConfigurationService
{
    public function __construct(
        private readonly PaymentMethodAvailabilityService $availability,
        private readonly StorefrontCurrencyContext $currency,
    ) {
    }

    /**
     * @param array{currency: string, amount: int, address?: array<string, mixed>} $context
     * @return array<string, array<string, mixed>>
     */
    public function checkoutMethods(array $context): array
    {
        return $this->availability->checkoutMethods($context);
    }

    /** @return array<string, string> */
    public function enabledStorefrontMethods(array $context = []): array
    {
        $context += [
            'currency' => $this->currency->current()['code'],
            'amount' => 0,
            'address' => [],
        ];

        return collect($this->checkoutMethods($context))
            ->mapWithKeys(fn (array $method, string $id): array => [$id => $method['label']])
            ->all();
    }

    /**
     * PDPs may only name methods that an operator has explicitly configured.
     * Checkout keeps its legacy fallback until the payment setup is first saved.
     *
     * @return array<string, string>
     */
    public function configuredStorefrontMethods(): array
    {
        return $this->enabledStorefrontMethods();
    }

    public function isEnabled(string $method): bool
    {
        $method = match ($method) {
            'simulated_card' => 'stripe',
            'klarna' => 'atome',
            default => $method,
        };

        return array_key_exists($method, $this->enabledStorefrontMethods());
    }
}
