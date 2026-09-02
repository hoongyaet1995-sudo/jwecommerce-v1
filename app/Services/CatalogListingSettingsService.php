<?php

namespace App\Services;

use App\Models\StoreSetting;

class CatalogListingSettingsService
{
    private const KEY = 'catalog_listing';

    /**
     * @return array{eyebrow: string, title: string, description: string}
     */
    public function live(): array
    {
        $value = StoreSetting::query()->where('key', self::KEY)->value('value');
        $data = is_array($value) && is_array($value['data'] ?? null) ? $value['data'] : [];

        return $this->normalize($data);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{eyebrow: string, title: string, description: string}
     */
    public function save(array $input): array
    {
        $data = validator($input, [
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $settings = $this->normalize($data);

        StoreSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => ['data' => $settings]],
        );

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{eyebrow: string, title: string, description: string}
     */
    private function normalize(array $data): array
    {
        return [
            'eyebrow' => trim((string) ($data['eyebrow'] ?? '')),
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
        ];
    }
}
