<?php

namespace App\Services\Search;

use App\Models\PageDocument;
use App\Models\SearchEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchProducer
{
    public function saved(string $kind, $model): void
    {
        if (! Schema::hasTable('search_entries')) {
            return;
        }
        Cache::forget('search:sitemap');
        $name = $kind === 'category' ? 'name' : 'title';
        $fields = [$name, 'description', 'subtitle', 'seo_title', 'seo_description', 'status', 'is_active', 'handle', 'slug'];
        if (! $model->wasChanged($fields) && ! $model->wasRecentlyCreated) {
            return;
        }
        $slugField = $kind === 'product' ? 'handle' : 'slug';
        $old = $model->getRawOriginal($slugField);
        if ($model->wasChanged($slugField) && $old && $old !== $model->$slugField) {
            $prefix = match ($kind) {
                'product' => 'products','category' => 'categories','collection' => 'collections'
            };
            DB::table('search_redirects')->updateOrInsert(['from_path' => '/'.$prefix.'/'.$old], ['kind' => $kind, 'entity_id' => $model->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function pageSaved(int $id, string $source): void
    {
        if ($source === 'ai_apply' || ! Schema::hasTable('search_entries')) {
            return;
        }
        $draft = PageDocument::where('page_id', $id)->where('stage', 'draft')->first();
        if ($draft) {
            $document = $draft->document;
            $policy = $document['seoSettings'] ?? [];
            $entry = SearchEntry::where('kind', 'page')->where('entity_id', $id)->where('stage', 'draft')->first();
            if ($entry) {
                $entry->forceFill(['version' => $entry->version + ($source === 'restore' ? 1 : 0), 'title' => ($policy['titleLocked'] ?? false) ? ($document['seoTitle'] ?? $entry->title) : $entry->title, 'description' => ($policy['descriptionLocked'] ?? false) ? ($document['seoDescription'] ?? $entry->description) : $entry->description, 'title_locked' => $policy['titleLocked'] ?? false, 'description_locked' => $policy['descriptionLocked'] ?? false, 'excluded' => $policy['excluded'] ?? false])->save();
            }
        }
    }

    public function pagePublished(int $id, ?string $oldSlug, string $newSlug): void
    {
        if (! Schema::hasTable('search_entries')) {
            return;
        }
        Cache::forget('search:sitemap');
        if ($oldSlug && $oldSlug !== $newSlug) {
            DB::table('search_redirects')->updateOrInsert(['from_path' => '/pages/'.$oldSlug], ['kind' => 'page', 'entity_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $entry = SearchEntry::where('kind', 'page')->where('entity_id', $id)->where('stage', 'draft')->first();
        if ($entry) {
            $values = $entry->getAttributes();
            unset($values['id'],$values['created_at'],$values['updated_at']);
            $values['stage'] = 'published';
            SearchEntry::updateOrCreate(['kind' => 'page', 'entity_id' => $id, 'stage' => 'published'], $values);
        }
    }

    public function productContentSaved(int $id): void
    {
        if (! Schema::hasTable('search_entries')) {
            return;
        }
        Cache::forget('search:sitemap');
    }
}
