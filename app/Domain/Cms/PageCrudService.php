<?php

namespace App\Domain\Cms;

use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

final class PageCrudService
{
    public function __construct(
        private readonly PageDocumentContract $contract,
        private readonly PageDocumentJsonCodec $documents,
        private readonly MediaReferenceSynchronizer $media,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws ValidationException
     */
    public function create(array $document, string $source = 'created'): Page
    {
        $validated = $this->documents->canonicalizeDocument(
            $this->contract->validate($document),
        );

        try {
            return DB::transaction(function () use ($validated, $source): Page {
                $document = $validated;
                $document['slug'] = $this->uniqueDraftSlug($validated['slug']);

                $page = Page::query()->create([
                    'status' => 'draft',
                    'published_at' => null,
                    'archived_at' => null,
                ]);

                $draft = $page->documents()->create([
                    'stage' => PageDocument::STAGE_DRAFT,
                    'slug' => $document['slug'],
                    'schema_version' => PageDocumentContract::VERSION,
                    'lock_version' => 1,
                    'content_hash' => PageDocumentHasher::hash($document),
                    'document' => $document,
                ]);
                app(PageRevisionService::class)->capture($draft, $source);
                app(\App\Services\Search\SearchProducer::class)->pageSaved((int)$page->id,'created');
                $this->media->sync($draft);

                return $page->load(['draftDocument', 'publishedDocument']);
            });
        } catch (QueryException $exception) {
            $this->throwStableSlugValidation($exception);

            throw $exception;
        }
    }

    public function duplicate(Page $page): Page
    {
        try {
            return DB::transaction(function () use ($page): Page {
                $source = Page::query()->lockForUpdate()->findOrFail($page->getKey());
                $sourceDraft = PageDocument::query()
                    ->where('page_id', $source->getKey())
                    ->where('stage', PageDocument::STAGE_DRAFT)
                    ->lockForUpdate()
                    ->firstOrFail();

                $copy = Page::query()->create([
                    'status' => 'draft',
                    'published_at' => null,
                    'archived_at' => null,
                ]);
                $document = $sourceDraft->document;
                $document['title'] = $document['title'].' (Copy)';
                $document['slug'] = $this->uniqueDraftSlug($document['slug'], copy: true);
                $document['sections'] = $this->freshSectionIds($document['sections'], $copy->getKey());
                $document = $this->documents->canonicalizeDocument(
                    $this->contract->validate($document),
                );

                $copyDraft = $copy->documents()->create([
                    'stage' => PageDocument::STAGE_DRAFT,
                    'slug' => $document['slug'],
                    'schema_version' => PageDocumentContract::VERSION,
                    'lock_version' => 1,
                    'content_hash' => PageDocumentHasher::hash($document),
                    'document' => $document,
                ]);
                app(PageRevisionService::class)->capture($copyDraft, 'created');
                $this->media->sync($copyDraft);

                return $copy->load(['draftDocument', 'publishedDocument']);
            });
        } catch (QueryException $exception) {
            $this->throwStableSlugValidation($exception);

            throw $exception;
        }
    }

    public function delete(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            app(HomepageSelection::class)->assertCanRetire($page);
            $lockedPage = Page::query()
                ->lockForUpdate()
                ->findOrFail($page->getKey());
            $historyAssets = DB::table('page_revision_media')->whereIn('revision_id', DB::table('page_revisions')->where('page_id', $lockedPage->id)->select('id'))->pluck('asset_id');
            $this->media->removeReferencesForPage($lockedPage);
            $lockedPage->delete();
            app(\App\Domain\Media\ManagedMediaOrphanState::class)->refresh(\App\Models\CmsMediaAsset::query()->whereIn('id', $historyAssets)->get());
        });
    }

    private function uniqueDraftSlug(mixed $requestedSlug, bool $copy = false): string
    {
        $base = Str::slug(is_string($requestedSlug) ? $requestedSlug : '');
        $base = $base !== '' ? $base : 'page';
        $base = Str::limit($base, 255, '');
        $copyBase = $copy ? $this->withSuffix($base, '-copy') : $base;
        $candidate = $copyBase;
        $suffix = 2;

        while (PageDocument::query()
            ->where('stage', PageDocument::STAGE_DRAFT)
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $this->withSuffix($copyBase, '-'.$suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function withSuffix(string $base, string $suffix): string
    {
        return substr($base, 0, 255 - strlen($suffix)).$suffix;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function freshSectionIds(array $sections, int|string $pageId): array
    {
        $usedIds = [];

        foreach ($sections as $section) {
            if (isset($section['id']) && is_string($section['id'])) {
                $usedIds[$section['id']] = true;
            }
        }

        foreach ($sections as $index => &$section) {
            $name = "https://jwstudio.local/cms/pages/{$pageId}/sections/{$index}";
            $collision = 0;

            do {
                $candidateName = $collision === 0 ? $name : "{$name}/collision/{$collision}";
                $candidate = Uuid::uuid5(Uuid::NAMESPACE_URL, $candidateName)->toString();
                $collision++;
            } while (isset($usedIds[$candidate]));

            $section['id'] = $candidate;
            $usedIds[$candidate] = true;
        }
        unset($section);

        return $sections;
    }

    /** @throws ValidationException */
    private function throwStableSlugValidation(QueryException $exception): void
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'page_documents_stage_slug_unique')
            || str_contains($message, 'page_documents.stage, page_documents.slug')) {
            throw ValidationException::withMessages([
                'slug' => ['This slug is already used by another draft page.'],
            ]);
        }
    }
}
