<?php

namespace App\Support;

use App\Models\StoreSetting;

class ShopBrand
{
    public function configuredLogo(): ?string
    {
        $logo=trim((string)data_get(StoreSetting::where('key','shop_profile')->value('value'),'data.shopLogo',''));
        return $logo !== '' ? $logo : null;
    }

    public function logo(): string
    {
        return data_get(StoreSetting::where('key', 'shop_profile')->value('value'), 'data.shopLogo') ?: asset('images/source/jwstudio_logo_1786766390670.jpg');
    }

    private ?string $resolvedName = null;

    public function name(): string
    {
        if ($this->resolvedName !== null) {
            return $this->resolvedName;
        }

        return $this->resolvedName = app(\App\Services\Search\BusinessIdentity::class)->read()['name'];
    }
}
