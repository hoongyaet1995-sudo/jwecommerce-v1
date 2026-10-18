<?php

namespace App\Services\Search;

use App\Domain\Cms\HomepageSelection;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\Product;
use App\Models\SearchEntry;
use App\Support\ShopBrand;
use Illuminate\Support\Str;

class SearchMetadata
{
    public function model(string $kind, int $id)
    {
        $class = match ($kind) {
            'product' => Product::class,'category' => Category::class,'collection' => Collection::class,'page' => Page::class,default => abort(404)
        };

        return $class::findOrFail($id);
    }

    public function stage(string $kind, string $stage = 'live'): string
    {
        return $kind === 'page' ? ($stage === 'published' ? 'published' : 'draft') : 'live';
    }

    public function facts(string $kind, int $id, string $stage = 'live'): array
    {
        $m = $this->model($kind, $id);
        $d = $kind === 'page' ? PageDocument::where('page_id', $id)->where('stage', $this->stage($kind, $stage))->firstOrFail()->document : [];
        $name = $kind === 'page' ? ($d['title'] ?? 'Page') : ($kind === 'category' ? $m->name : $m->title);
        $description = $kind === 'page' ? ($d['description'] ?? '') : ($kind === 'product' ? trim(($m->subtitle ?? '').' '.($m->description ?? '')) : $m->description);
        $facts = ['name' => $this->plain($name, 255), 'description' => $this->plain($description, 4000), 'websiteName' => app(ShopBrand::class)->name(), 'generationPolicy' => 'search-v2'];
        if ($kind === 'page') {
            $visible = array_values(array_filter($d['sections'] ?? [], fn ($s) => ($s['isVisible'] ?? false) === true));
            $facts['content'] = $this->text($visible);
        }
        if ($kind === 'product') {
            $facts['purchase_mode'] = $m->purchase_mode;
            $content = $m->pdpContent;
            if ($content) {
                $facts['productContent'] = $this->text(['story' => $content->story ?? [], 'faqs' => $content->faqs ?? [], 'highlights' => $content->highlights ?? []]);
            }
        }

        return $facts;
    }

    public function hash(array $facts): string
    {
        return hash('sha256', json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function plain(?string $value, int $limit = 1000): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), $limit, '');
    }

    private function text(array $sections): string
    {
        $texts = [];
        $visit = function ($value, $key = '') use (&$visit, &$texts) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $visit($v, (string) $k);
                }
            } elseif (is_string($value) && preg_match('/^(title|heading|description|text|answer|question|body|label|content)$/', $key)) {
                $texts[] = $this->plain($value, 2000);
            }
        };
        $visit($sections);

        return Str::limit(implode("\n", $texts), 12000, '');
    }

    public function for(string $kind, int $id, string $stage = 'live'): array
    {
        $stage = $this->stage($kind, $stage);
        $m = $this->model($kind, $id);
        $facts = $this->facts($kind, $id, $stage);
        $hash = $this->hash($facts);
        $entry = SearchEntry::where(compact('kind'))->where('entity_id', $id)->where('stage', $stage)->first();
        $doc = $kind === 'page' ? PageDocument::where('page_id', $id)->where('stage', $stage)->firstOrFail()->document : [];
        $legacyTitle = $kind === 'page' ? ($doc['seoTitle'] ?? null) : $m->seo_title;
        $legacyDescription = $kind === 'page' ? ($doc['seoDescription'] ?? null) : $m->seo_description;
        $valid = $entry && $entry->source_hash === $hash;
        $baseDescription = $this->plain($facts['description'] ?: ($facts['content'] ?? ''), 320);
        $title = $entry?->title_locked ? $entry->title : ($valid && $entry->source === 'ai' ? $entry->title : (! $entry || $entry->source === 'legacy' ? $legacyTitle : null));
        $description = $entry?->description_locked ? $entry->description : ($valid && $entry->source === 'ai' ? $entry->description : (! $entry || $entry->source === 'legacy' ? $legacyDescription : null));
        if ($kind === 'page' && ($valid || ! $entry || $entry->source === 'legacy')) {
            $title = $legacyTitle;
            $description = $legacyDescription;
        }
        $title = $this->plain($title ?: $facts['name'], 255);
        $brand = $facts['websiteName'];
        $rawTitle=$title;
        if ($brand !== '' && ! str_ends_with($title, ' | '.$brand)) {
            $title .= ' | '.$brand;
        }

        $titleLocked = $kind === 'page' ? ($doc['seoSettings']['titleLocked'] ?? false) : ($entry?->title_locked ?? false);
        $descriptionLocked = $kind === 'page' ? ($doc['seoSettings']['descriptionLocked'] ?? false) : ($entry?->description_locked ?? false);

        return ['title' => $title, 'rawTitle'=>$rawTitle,'description' => $this->plain($description ?: $baseDescription), 'source' => $titleLocked || $descriptionLocked ? 'manual' : ($valid ? $entry->source : ($legacyTitle && ! $entry ? 'legacy' : 'system')), 'hash' => $hash, 'version' => $entry?->version ?? 0, 'titleLocked' => $titleLocked, 'descriptionLocked' => $descriptionLocked, 'excluded' => $kind === 'page' ? ($doc['seoSettings']['excluded'] ?? false) : ($entry?->excluded ?? false), 'topics' => $valid ? ($entry->topics ?? []) : []];
    }

    public function path(string $kind, int $id): string
    {
        $m = $this->model($kind, $id);
        if ($kind === 'page') {
            if (app(HomepageSelection::class)->id() === (string) $id) {
                return '/';
            }

return '/pages/'.PageDocument::where('page_id', $id)->where('stage', 'published')->value('slug');
        }

        return match ($kind) {
            'product' => '/products/'.$m->handle,'category' => '/categories/'.$m->slug,'collection' => '/collections/'.$m->slug
        };
    }

    public function public(string $kind, int $id): bool
    {
        $m = $this->model($kind, $id);

        return match ($kind) {
            'page' => $m->status === 'published' && $m->publishedDocument !== null,'product' => $m->status === 'active','category' => (bool) $m->is_active,'collection' => (bool) $m->is_active
        };
    }
}
