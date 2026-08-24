<?php

namespace App\Support;

use App\Models\StoreSetting;
use Illuminate\Support\Arr;

class CurrencySettings
{
    public const KEY = 'currencies';

    /**
     * Return the currency matrix from MySQL, creating the one-time operating
     * baseline only for installations that predate the currency setting.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = data_get(StoreSetting::query()->where('key', self::KEY)->value('value'), 'data');

        if (! is_array($stored) || $stored === []) {
            $stored = self::defaults();
            StoreSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => ['data' => $stored]]);
        }

        return $this->normalize($stored);
    }

    /** @return array<int, array<string, mixed>> */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), static fn (array $currency): bool => $currency['isEnabled']));
    }

    /** @return array<string, mixed> */
    public function default(): array
    {
        $enabled = $this->enabled();

        foreach ($enabled as $currency) {
            if ($currency['isDefault']) {
                return $currency;
            }
        }

        return $enabled[0] ?? $this->all()[0];
    }

    /**
     * @param array<int, mixed> $currencies
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $currencies): array
    {
        return collect($currencies)
            ->filter(static fn ($currency): bool => is_array($currency))
            ->map(static function (array $currency): array {
                $code = strtoupper(trim((string) Arr::get($currency, 'code')));

                return [
                    'id' => trim((string) Arr::get($currency, 'id', $code)),
                    'code' => $code,
                    'title' => trim((string) Arr::get($currency, 'title')),
                    'symbolLeft' => (string) Arr::get($currency, 'symbolLeft', ''),
                    'symbolRight' => (string) Arr::get($currency, 'symbolRight', ''),
                    'decimalPlaces' => (int) Arr::get($currency, 'decimalPlaces', 2),
                    'exchangeRate' => (float) Arr::get($currency, 'exchangeRate', 1),
                    'isDefault' => (bool) Arr::get($currency, 'isDefault', false),
                    'autoUpdate' => (bool) Arr::get($currency, 'autoUpdate', false),
                    'lastUpdated' => (string) Arr::get($currency, 'lastUpdated', ''),
                    'isEnabled' => (bool) Arr::get($currency, 'isEnabled', false),
                ];
            })
            ->filter(static fn (array $currency): bool => $currency['id'] !== '' && $currency['code'] !== '' && $currency['title'] !== '')
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public static function defaults(): array
    {
        return [
            ['id' => 'MYR', 'code' => 'MYR', 'title' => 'Malaysian Ringgit', 'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1.0, 'isDefault' => true, 'autoUpdate' => false, 'lastUpdated' => '2026-08-17 08:00:00 (Base Currency)', 'isEnabled' => true],
            ['id' => 'SGD', 'code' => 'SGD', 'title' => 'Singapore Dollar', 'symbolLeft' => 'S$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.3025, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-17 06:30:15 (MAS FX Feed)', 'isEnabled' => true],
            ['id' => 'USD', 'code' => 'USD', 'title' => 'US Dollar', 'symbolLeft' => '$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2245, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-17 06:30:15 (Oanda FX Feed)', 'isEnabled' => true],
            ['id' => 'EUR', 'code' => 'EUR', 'title' => 'Euro', 'symbolLeft' => '€', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2085, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-17 06:30:15 (ECB FX Feed)', 'isEnabled' => true],
            ['id' => 'IDR', 'code' => 'IDR', 'title' => 'Indonesian Rupiah', 'symbolLeft' => 'Rp ', 'symbolRight' => '', 'decimalPlaces' => 0, 'exchangeRate' => 3580.0, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-17 06:30:15 (BI FX Feed)', 'isEnabled' => true],
            ['id' => 'THB', 'code' => 'THB', 'title' => 'Thai Baht', 'symbolLeft' => '฿', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 8.15, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-17 06:30:15 (BOT FX Feed)', 'isEnabled' => true],
            ['id' => 'CNY', 'code' => 'CNY', 'title' => 'Chinese Yuan Renminbi', 'symbolLeft' => '¥', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1.625, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-17 06:30:15 (PBOC FX Feed)', 'isEnabled' => true],
            ['id' => 'GBP', 'code' => 'GBP', 'title' => 'British Pound Sterling', 'symbolLeft' => '£', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.178, 'isDefault' => false, 'autoUpdate' => true, 'lastUpdated' => '2026-08-17 06:30:15 (BOE FX Feed)', 'isEnabled' => false],
        ];
    }
}
