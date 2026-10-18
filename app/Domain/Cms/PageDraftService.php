<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Exceptions\CmsVersionConflict;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PageDraftService
{
    public function __construct(
        private readonly PageDocumentContract $contract,
        private readonly PageDocumentJsonCodec $documents,
        private readonly MediaReferenceSynchronizer $media,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws CmsVersionConflict
     * @throws ValidationException
     */
    public function save(Page $page, array $document, int $expectedVersion, string $source = 'autosave', ?int $restoredFrom = null): PageDocument
    {
        try {
            return DB::transaction(function () use ($page, $document, $expectedVersion, $source, $restoredFrom): PageDocument {
                $draft = PageDocument::query()
                    ->where('page_id', $page->getKey())
                    ->where('stage', PageDocument::STAGE_DRAFT)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($draft->lock_version !== $expectedVersion) {
                    throw CmsVersionConflict::for($draft->lock_version);
                }

                app(PageRevisionService::class)->baseline($page);

                $validated = $this->documents->canonicalizeDocument(
                    $this->contract->validate($document),
                );
                $this->assertSafeAvailableSlug($draft, $validated['slug']);
                $nextVersion = $draft->lock_version + 1;

                $updated = PageDocument::query()
                    ->whereKey($draft->getKey())
                    ->where('lock_version', $expectedVersion)
                    ->update([
                        'slug' => $validated['slug'],
                        'document' => $this->documents->encodeDocument($validated),
                        'content_hash' => PageDocumentHasher::hash($validated),
                        'lock_version' => $nextVersion,
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    $currentVersion = (int) PageDocument::query()
                        ->whereKey($draft->getKey())
                        ->value('lock_version');

                    throw CmsVersionConflict::for($currentVersion);
                }

                $saved = PageDocument::query()->findOrFail($draft->getKey());
                app(PageRevisionService::class)->capture($saved, $source, null, $restoredFrom);
                $this->media->sync($saved);

                return $saved;
            });
        } catch (QueryException $exception) {
            if ($this->isDraftSlugConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'slug' => ['This slug is already used by another draft page.'],
                ]);
            }

            throw $exception;
        }
    }

    /** @throws ValidationException */
    private function assertSafeAvailableSlug(PageDocument $draft, mixed $slug): void
    {
        if (! is_string($slug)
            || strlen($slug) > 255
            || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) !== 1) {
            throw ValidationException::withMessages([
                'slug' => ['The slug must use lowercase letters, numbers, and single hyphens only.'],
            ]);
        }

        $exists = PageDocument::query()
            ->where('stage', PageDocument::STAGE_DRAFT)
            ->where('slug', $slug)
            ->whereKeyNot($draft->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slug' => ['This slug is already used by another draft page.'],
            ]);
        }
    }

    private function isDraftSlugConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'page_documents_stage_slug_unique')
            || str_contains($message, 'page_documents.stage, page_documents.slug');
    }
}
