<?php

namespace App\Services\Search;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchSettings
{
    public function read(): array
    {
        return array_intersect_key(array_replace(['version' => 0, 'mode' => 'off', 'preview' => false], StoreSetting::where('key', 'search_visibility')->value('value') ?? []), array_flip(['version', 'mode', 'preview']));
    }

    public function save(array $data): array
    {
        return DB::transaction(function () use ($data) {
            StoreSetting::firstOrCreate(['key' => 'search_visibility'], ['value' => ['version' => 0]]);
            $row = StoreSetting::where('key', 'search_visibility')->lockForUpdate()->firstOrFail();
            $old = $row->value;
            abort_if(($old['version'] ?? 0) !== $data['version'], 409, 'Search preferences changed. Reload before saving.');
            $next = array_intersect_key($data, array_flip(['mode', 'preview']));
            $next['version'] = $data['version'] + 1;
            $row->value = $next;
            $row->save();
            Cache::forget('search:sitemap');

            return $this->read();
        });
    }

    public function origin(): string
    {
        // Deployment configuration only; never use request Host to determine canonical origin.
        $url = rtrim((string) config('app.url'), '/');
        $parts = parse_url($url);
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
