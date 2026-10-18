<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Exceptions\CmsPublicationBlocked;
use App\Domain\Cms\Exceptions\CmsRuntimeReferenceInvalid;
use App\Domain\Cms\Exceptions\CmsVersionConflict;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PagePublicationService
{
    private const PUBLISHED_STAGE_SLUG_INDEX = 'page_documents_stage_slug_unique';

    public function __construct(
        private readonly PageDocumentContract $contract,
        private readonly RuntimeAdapterRegistry $adapters,
        private readonly MediaReferenceSynchronizer $media,
    ) {}

    /**
     * Promote the complete canonical draft at one locked transaction boundary.
     *
     * @throws CmsPublicationBlocked
     * @throws CmsRuntimeReferenceInvalid
     * @throws CmsVersionConflict
     */
    public function publish(Page $page, int $expectedDraftVersion): Page
    {
        try {
            return DB::transaction(function () use ($page, $expectedDraftVersion): Page {
                $lockedPage = Page::query()
                    ->lockForUpdate()
                    ->findOrFail($page->getKey());
                $draft = PageDocument::query()
                    ->where('page_id', $lockedPage->getKey())
                    ->where('stage', PageDocument::STAGE_DRAFT)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($draft->lock_version !== $expectedDraftVersion) {
                    throw CmsVersionConflict::for($draft->lock_version);
                }

                $document = $draft->document;
                $facts = $this->adapters->factsFor($document);

                try {
                    $this->contract->assertPublishable($document, $facts);
                } catch (CmsRuntimeReferenceInvalid $exception) {
                    throw $exception;
                } catch (ValidationException $exception) {
                    throw CmsPublicationBlocked::withMessages($exception->errors());
                }

                $this->assertPublishedSlugAvailable($lockedPage, $draft->slug);

                $published = PageDocument::query()->updateOrCreate(
                    [
                        'page_id' => $lockedPage->getKey(),
                        'stage' => PageDocument::STAGE_PUBLISHED,
                    ],
                    [
                        'slug' => $draft->slug,
                        'schema_version' => $draft->schema_version,
                        'lock_version' => $draft->lock_version,
                        'content_hash' => $draft->content_hash,
                        'document' => $document,
                    ],
                );
                app(PageRevisionService::class)->capture($published, 'published');
                $this->media->sync($published);

                $lockedPage->forceFill([
                    'status' => 'published',
                    'published_at' => now(),
                    'archived_at' => null,
                ])->saveOrFail();

                return $lockedPage->fresh()->load(['draftDocument', 'publishedDocument']);
            });
        } catch (QueryException $exception) {
            if ($this->isPublishedSlugConstraintViolation($exception)) {
                throw $this->publishedSlugConflict();
            }

            throw $exception;
        }
    }

    public function unpublish(Page $page): Page
    {
        return DB::transaction(function () use ($page): Page {
            app(HomepageSelection::class)->assertCanRetire($page);
            $lockedPage = Page::query()
                ->lockForUpdate()
                ->findOrFail($page->getKey());
            PageDocument::query()
                ->where('page_id', $lockedPage->getKey())
                ->where('stage', PageDocument::STAGE_DRAFT)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPage->forceFill([
                'status' => 'draft',
                'archived_at' => null,
            ])->saveOrFail();

            return $lockedPage->fresh()->load(['draftDocument', 'publishedDocument']);
        });
    }

    public function archive(Page $page): Page
    {
        return DB::transaction(function () use ($page): Page {
            app(HomepageSelection::class)->assertCanRetire($page);
            $lockedPage = Page::query()
                ->lockForUpdate()
                ->findOrFail($page->getKey());
            PageDocument::query()
                ->where('page_id', $lockedPage->getKey())
                ->where('stage', PageDocument::STAGE_DRAFT)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPage->forceFill([
                'status' => 'archived',
                'archived_at' => now(),
            ])->saveOrFail();

            return $lockedPage->fresh()->load(['draftDocument', 'publishedDocument']);
        });
    }

    /** @throws CmsPublicationBlocked */
    private function assertPublishedSlugAvailable(Page $page, string $slug): void
    {
        $owner = PageDocument::query()
            ->where('stage', PageDocument::STAGE_PUBLISHED)
            ->where('slug', $slug)
            ->where('page_id', '!=', $page->getKey())
            ->lockForUpdate()
            ->first(['id']);

        if ($owner !== null) {
            throw $this->publishedSlugConflict();
        }
    }

    private function publishedSlugConflict(): CmsPublicationBlocked
    {
        return CmsPublicationBlocked::withMessages([
            'slug' => ['This slug is already used by another published page.'],
        ]);
    }

    private function isPublishedSlugConstraintViolation(QueryException $exception): bool
    {
        $details = $exception->getConnectionDetails();
        $driver = strtolower((string) ($details['driver'] ?? $exception->getConnectionName()));
        $errorInfo = is_array($exception->errorInfo ?? null) ? $exception->errorInfo : [];
        $sqlState = strtoupper((string) ($errorInfo[0] ?? $exception->getCode()));
        $driverCode = is_numeric($errorInfo[1] ?? null) ? (int) $errorInfo[1] : null;
        $driverMessage = strtolower((string) (
            $errorInfo[2]
                ?? $exception->getPrevious()?->getMessage()
                ?? ''
        ));

        if (str_contains($driver, 'mysql') || str_contains($driver, 'mariadb')) {
            return $sqlState === '23000'
                && $driverCode === 1062
                && $this->mysqlDuplicateTargetsPublishedSlug($driverMessage);
        }

        if (str_contains($driver, 'sqlite')) {
            return $sqlState === '23000'
                && in_array($driverCode, [19, 2067], true)
                && $this->sqliteDuplicateTargetsPublishedSlug($driverMessage);
        }

        return false;
    }

    private function mysqlDuplicateTargetsPublishedSlug(string $driverMessage): bool
    {
        $quotedIdentifier = '(?:\'[^\']+\'|"[^"]+"|`[^`]+`)';

        if (preg_match(
            '/\bfor\s+key\s+(?<key>'.$quotedIdentifier.'(?:\s*\.\s*'.$quotedIdentifier.')*)\s*\.?\s*$/i',
            $driverMessage,
            $matches,
        ) !== 1) {
            return false;
        }

        $qualifiedKey = str_replace(["'", '"', '`'], '', $matches['key']);
        $identifiers = preg_split('/\s*\.\s*/', $qualifiedKey);
        $terminalIdentifier = is_array($identifiers) ? end($identifiers) : false;

        return $terminalIdentifier === self::PUBLISHED_STAGE_SLUG_INDEX;
    }

    private function sqliteDuplicateTargetsPublishedSlug(string $driverMessage): bool
    {
        if (preg_match(
            '/\bunique constraint failed:\s*(?<columns>[a-z0-9_]+\.[a-z0-9_]+(?:\s*,\s*[a-z0-9_]+\.[a-z0-9_]+)*)\s*$/i',
            $driverMessage,
            $matches,
        ) !== 1) {
            return false;
        }

        $columns = preg_split('/\s*,\s*/', strtolower($matches['columns']));

        return $columns === [
            'page_documents.stage',
            'page_documents.slug',
        ];
    }
}
