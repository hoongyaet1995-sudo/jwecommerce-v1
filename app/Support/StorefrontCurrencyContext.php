<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The sole storefront presentation context for an amount stored in base cents.
 *
 * Catalog, cart, checkout, and order arithmetic intentionally remain in the
 * store's base currency. This class only resolves the shopper's enabled session
 * preference and formats that base amount for display.
 */
class StorefrontCurrencyContext
{
    public const SESSION_KEY = 'storefront_currency';

    public function __construct(
        private readonly CurrencySettings $settings,
        private readonly Request $request,
    ) {
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $selectedCode = strtoupper(trim((string) $this->request->session()->get(self::SESSION_KEY, '')));

        foreach ($this->settings->enabled() as $currency) {
            if ($currency['code'] === $selectedCode) {
                return $currency;
            }
        }

        return $this->settings->default();
    }

    /** @return array{code: string, exchange_rate: float} */
    public function snapshot(): array
    {
        $currency = $this->current();

        return [
            'code' => $currency['code'],
            'exchange_rate' => (float) $currency['exchangeRate'],
        ];
    }

    public function format(int|float|null $baseCents): string
    {
        return $this->formatWith($baseCents, $this->current());
    }

    /**
     * Format a historical order from its immutable checkout snapshot. This
     * deliberately ignores the shopper's current session preference.
     */
    public function formatSnapshot(int|float|null $baseCents, ?string $code, int|float|null $exchangeRate): string
    {
        $currency = collect($this->settings->all())
            ->first(fn (array $entry): bool => $entry['code'] === strtoupper((string) $code))
            ?? $this->current();

        $currency['exchangeRate'] = $exchangeRate === null ? ($currency['exchangeRate'] ?? 1) : (float) $exchangeRate;

        return $this->formatWith($baseCents, $currency);
    }

    /** @param array<string, mixed> $currency */
    public function formatWith(int|float|null $baseCents, array $currency): string
    {
        $decimals = max(0, (int) ($currency['decimalPlaces'] ?? 2));
        $amount = ((float) ($baseCents ?? 0) / 100) * (float) ($currency['exchangeRate'] ?? 1);
        $formatted = number_format($amount, $decimals, '.', ',');

        return (string) ($currency['symbolLeft'] ?? '').$formatted.(string) ($currency['symbolRight'] ?? '');
    }
}
