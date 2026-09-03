<?php

namespace App\Support;

use App\Models\StoreSetting;

class ReturnPolicySettings
{
    public const KEY = 'return_policy';

    /** @return array{enabled: bool, returnWindowDays: int, policySummary: string} */
    public function get(): array
    {
        $stored = data_get(StoreSetting::query()->where('key', self::KEY)->value('value'), 'data', []);

        return [
            'enabled' => (bool) data_get($stored, 'enabled', true),
            'returnWindowDays' => max(1, min(365, (int) data_get($stored, 'returnWindowDays', 30))),
            'policySummary' => (string) data_get($stored, 'policySummary', 'Eligible delivered items may be returned or replaced within 30 days.'),
        ];
    }

    /** @param array{enabled: bool, returnWindowDays: int, policySummary: string} $policy */
    public function put(array $policy): array
    {
        StoreSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => ['data' => $policy]]);

        return $this->get();
    }
}
