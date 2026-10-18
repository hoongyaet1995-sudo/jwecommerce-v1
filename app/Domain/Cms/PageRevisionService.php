<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Exceptions\CmsVersionConflict;
use App\Domain\Media\ManagedMediaOrphanState;
use App\Models\CmsMediaAsset;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class PageRevisionService
{
    public function capture(PageDocument $d, string $kind = 'autosave', ?string $name = null, ?int $from = null): int
    {
        $last = DB::table('page_revisions')->where('page_id', $d->page_id)->latest('id')->first();
        if ($kind === 'autosave' && $last && $last->content_hash === $d->content_hash) {
            return (int) $last->id;
        }
        if ($kind === 'restore' && $from) {
            $sourceNumber = DB::table('page_revisions')->where('page_id', $d->page_id)->where('id', $from)->value('number');
            $name = $sourceNumber ? 'Restored from v'.$sourceNumber : 'Restored snapshot';
        }
        $id = DB::table('page_revisions')->insertGetId(['page_id' => $d->page_id, 'number' => ($last->number ?? 0) + 1, 'draft_version' => $d->lock_version, 'schema_version' => $d->schema_version, 'content_hash' => $d->content_hash, 'document' => app(PageDocumentJsonCodec::class)->encodeDocument($d->document), 'kind' => $kind, 'name' => $name, 'summary' => $last && $last->content_hash === $d->content_hash ? 'Saved checkpoint · content unchanged' : $this->summary($last ? json_decode($last->document, true) : null, $d->document), 'actor_id' => Auth::id(), 'actor_name' => Auth::user()?->name ?? 'System', 'restored_from' => $from, 'created_at' => now()]);
        $urls = [];
        $walk = function ($value) use (&$walk, &$urls) {
            if (is_array($value) || is_object($value)) {
                foreach ($value as $v) {
                    $walk($v);
                }
            } elseif (is_string($value) && preg_match('#^(?:/|https?://)#i', $value)) {
                $urls[] = $value;
            }
        };
        $walk($d->document);
        foreach (CmsMediaAsset::query()->whereIn('url', array_unique($urls))->pluck('id') as $asset) {
            DB::table('page_revision_media')->insert(['revision_id' => $id, 'asset_id' => $asset]);
        }
        // Protected checkpoints never count toward the rolling automatic history cap.
        $expired = DB::table('page_revisions')->where('page_id', $d->page_id)->where('kind', 'autosave')->orderByDesc('id')->offset(100)->limit(1000)->pluck('id');
        if ($expired->isNotEmpty()) {
            $released = DB::table('page_revision_media')->whereIn('revision_id', $expired)->pluck('asset_id');
            DB::table('page_revisions')->whereIn('id', $expired)->delete();
            app(ManagedMediaOrphanState::class)->refresh(CmsMediaAsset::query()->whereIn('id', $released)->get());
        }

        return $id;
    }

    private function summary(?array $before, array $after): string
    {
        if (! $before) {
            return 'Initial page snapshot';
        }
        $changes = [];
        foreach (['title' => 'Page title', 'slug' => 'Page path', 'description' => 'Page description', 'style' => 'Page style', 'seoTitle' => 'SEO title', 'seoDescription' => 'SEO description', 'headerPresentation' => 'Header presentation'] as $key => $label) {
            if (json_encode($before[$key] ?? null) !== json_encode($after[$key] ?? null)) {
                $changes[] = $label.' updated';
            }
        }
        $old = array_column($before['sections'] ?? [], null, 'id');
        $new = array_column($after['sections'] ?? [], null, 'id');
        $added = count(array_diff_key($new, $old));
        $removed = count(array_diff_key($old, $new));
        $updated = 0;
        foreach ($new as $id => $section) {
            if (isset($old[$id]) && json_encode($old[$id]) !== json_encode($section)) {
                $updated++;
            }
        }
        if ($added) {
            $changes[] = $added.' section(s) added';
        }if ($removed) {
            $changes[] = $removed.' section(s) removed';
        }if ($updated) {
            $changes[] = $updated.' section(s) updated';
        }if (array_keys($old) !== array_keys($new) && ! $added && ! $removed) {
            $changes[] = 'Section order changed';
        }

        return implode(' · ', $changes) ?: 'Page settings updated';
    }

    public function baseline(Page $page): void
    {
        if (DB::table('page_revisions')->where('page_id', $page->id)->exists()) {
            return;
        }
        foreach ($page->documents()->get() as $d) {
            $this->capture($d, $d->stage === 'published' ? 'published' : 'baseline');
        }
    }

    public function checkpoint(Page $page, int $expected, string $kind, string $name): int
    {
        return DB::transaction(function () use ($page, $expected, $kind, $name) {
            $d = $page->documents()->where('stage', 'draft')->lockForUpdate()->firstOrFail();
            if ($d->lock_version !== $expected) {
                throw CmsVersionConflict::for($d->lock_version);
            }

            $existing = DB::table('page_revisions')->where('page_id', $page->id)->where('draft_version', $expected)->where('content_hash', $d->content_hash)->where('kind', $kind)->where('name', $name)->value('id');
            if ($existing) {
                return (int) $existing;
            }

            return $this->capture($d, $kind, $name);
        });
    }

    public function restore(Page $page, int $id, int $expected): PageDocument
    {
        return DB::transaction(function () use ($page, $id, $expected) {
            $r = $this->revision($page, $id);
            $current = $page->documents()->where('stage', 'draft')->lockForUpdate()->firstOrFail();
            if ($current->lock_version !== $expected) {
                throw CmsVersionConflict::for($current->lock_version);
            }
            abort_if($r->schema_version !== PageDocumentContract::VERSION, 422, 'This historical schema cannot be restored.');
            $this->capture($current, 'before_restore');
            $d = app(PageDraftService::class)->save($page, app(PageDocumentJsonCodec::class)->decodeDocument($r->document), $expected, 'restore', $id);

            return $d;
        });
    }

    public function revision(Page $page, int $id): object
    {
        return DB::table('page_revisions')->where('page_id', $page->id)->where('id', $id)->first() ?? abort(404);
    }

    public function list(Page $page, int $before = 0): array
    {
        $draft = $page->draftDocument;
        $live = $page->publishedDocument;
        $currentId = $draft ? DB::table('page_revisions')->where('page_id', $page->id)->where('content_hash', $draft->content_hash)->latest('id')->value('id') : null;
        $liveId = $live ? DB::table('page_revisions')->where('page_id', $page->id)->where('kind', 'published')->where('content_hash', $live->content_hash)->latest('id')->value('id') : null;

        $records = DB::table('page_revisions')->where('page_id', $page->id)->when($before, fn ($q) => $q->where('id', '<', $before))->orderByDesc('id')->limit(50)->get();
        $restoredVersions = DB::table('page_revisions')->where('page_id', $page->id)->whereIn('id', $records->pluck('restored_from')->filter())->pluck('number', 'id');

        return $records->map(fn ($r) => ['id' => (string) $r->id, 'version' => (int) $r->number, 'kind' => $r->kind, 'name' => $r->name, 'actor' => $r->actor_name ?? 'Former staff', 'createdAt' => $r->created_at, 'restoredFrom' => $r->restored_from, 'restoredFromVersion' => $restoredVersions[$r->restored_from] ?? null, 'isCurrent' => $currentId !== null && (int) $r->id === (int) $currentId, 'isLive' => $liveId !== null && (int) $r->id === (int) $liveId, 'hash' => $r->content_hash, 'summary' => $r->summary ?? 'Existing page snapshot'])->all();
    }
}
