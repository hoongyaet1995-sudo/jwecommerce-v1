<?php

namespace App\Support;

use App\Models\StoreSetting;

class ShopBrand
{
    private ?string $resolvedName = null;

    public function name(): string
    {
        if ($this->resolvedName !== null) {
            return $this->resolvedName;
        }

        $configuredName = trim((string) data_get(
            StoreSetting::query()->where('key', 'shop_name')->value('value'),
            'data',
            ''
        ));

        return $this->resolvedName = $configuredName !== '' ? $configuredName : 'JW Studio';
    }
}
