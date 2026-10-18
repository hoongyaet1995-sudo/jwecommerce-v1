<?php

namespace App\Services\Search;

use App\Models\StoreSetting;

/** Shop settings remain the single editable owner; no second SEO business profile. */
class BusinessIdentity
{
    public function read(): array
    {
        $values = StoreSetting::whereIn('key', ['shop_profile', 'shop_name'])->pluck('value', 'key');
        $profile = data_get($values->get('shop_profile'), 'data', []);
        $profile = is_array($profile) ? $profile : [];
        $name = trim((string) ($profile['shopName'] ?? ''));
        $legacy = trim((string) data_get($values->get('shop_name'), 'data', ''));

        return ['name' => $name ?: ($legacy ?: 'JW Studio'), 'name_source' => $name ? 'shop_profile.data.shopName' : ($legacy ? 'shop_name.data' : 'fallback'),
            'legacy_name_conflict' => $name !== '' && $legacy !== '' && $name !== $legacy,
            'logo' => trim((string) ($profile['shopLogo'] ?? '')),
            // Presence only: internal diagnostics must not export private business values.
            'missing' => array_values(array_filter(['shopName', 'tagline', 'email', 'phone', 'address', 'city', 'state', 'country'], fn ($field) => trim((string) ($profile[$field] ?? '')) === '')),
        ];
    }
}
