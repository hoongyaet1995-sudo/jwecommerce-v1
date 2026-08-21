<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Data\MigrationResult;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\PageSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class LegacyPageDocumentMigrator
{
    /** @var list<string> */
    private const METADATA_FIELDS = [
        'description',
        'template',
        'heroImage',
        'seoTitle',
        'seoDescription',
    ];

    public function __construct(
        private readonly PageDocumentContract $contract,
        private readonly PageDocumentJsonCodec $documents,
        private readonly MediaReferenceSynchronizer $media,
    ) {}

    public function migratePage(Page $page, bool $force = false): MigrationResult
    {
        if ($force && app()->environment('production')) {
            throw new LogicException('Forced legacy CMS document migration is forbidden in production.');
        }

        return DB::transaction(function () use ($page, $force): MigrationResult {
            $lockedPage = Page::query()
                ->whereKey($page->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPage->setRelation(
                'sections',
                $lockedPage->sections()->orderBy('position')->orderBy('id')->lockForUpdate()->get(),
            );

            $composition = $this->compose($lockedPage);
            $existingDocuments = $lockedPage->documents()->orderBy('stage')->lockForUpdate()->get();
            $existingResult = $this->resultFor($lockedPage, $composition, $existingDocuments);

            if ($existingDocuments->isNotEmpty() && ! $force) {
                if ($existingResult->canonicalVerified) {
                    return $existingResult->withOutcome(migrated: false, skipped: true);
                }

                throw new LogicException(
                    'Refusing to overwrite canonical documents for legacy page '
                    ."[{$lockedPage->getKey()}]: ".implode('; ', $existingResult->errors),
                );
            }

            if ($force) {
                $this->media->removeReferencesForPage($lockedPage);
                $lockedPage->documents()->delete();
            }

            $expectedStatus = $lockedPage->is_published ? 'published' : 'draft';
            DB::table('pages')->where('id', $lockedPage->getKey())->update([
                'status' => $expectedStatus,
            ]);

            $documentAttributes = [
                'slug' => $composition['document']['slug'],
                'schema_version' => PageDocumentContract::VERSION,
                'lock_version' => 1,
                'content_hash' => $composition['document_hash'],
                'document' => $composition['document'],
            ];

            $draft = $lockedPage->documents()->create($documentAttributes + [
                'stage' => PageDocument::STAGE_DRAFT,
            ]);
            $this->media->sync($draft);

            if ($composition['published_expected']) {
                $published = $lockedPage->documents()->create($documentAttributes + [
                    'stage' => PageDocument::STAGE_PUBLISHED,
                ]);
                $this->media->sync($published);
            }

            $verifiedPage = Page::query()
                ->with(['sections' => fn ($query) => $query->orderBy('position')->orderBy('id')])
                ->findOrFail($lockedPage->getKey());
            $verifiedComposition = $this->compose($verifiedPage);
            $result = $this->resultFor(
                $verifiedPage,
                $verifiedComposition,
                $verifiedPage->documents()->orderBy('stage')->get(),
            );

            if (! $result->canonicalVerified) {
                throw new LogicException(
                    'Legacy CMS document migration verification failed for page '
                    ."[{$lockedPage->getKey()}]: ".implode('; ', $result->errors),
                );
            }

            return $result->withOutcome(migrated: true, skipped: false);
        }, 3);
    }

    /**
     * Compose and validate a page without acquiring write locks or changing any row.
     */
    public function verifyPage(Page $page): MigrationResult
    {
        $sourcePage = Page::query()
            ->with(['sections' => fn ($query) => $query->orderBy('position')->orderBy('id')])
            ->findOrFail($page->getKey());
        $composition = $this->compose($sourcePage);

        return $this->resultFor(
            $sourcePage,
            $composition,
            $sourcePage->documents()->orderBy('stage')->get(),
        );
    }

    /**
     * @return array{
     *     document: array<string, mixed>,
     *     document_hash: string,
     *     source_hash: string,
     *     source_section_ids: list<string>,
     *     source_section_types: list<string>,
     *     source_section_key_sets: list<array{
     *         id: string,
     *         content_keys: list<string>,
     *         output_keys: list<string>,
     *         item_key_sets: list<list<string>>
     *     }>,
     *     key_loss_paths: list<string>,
     *     published_expected: bool,
     *     normalizations: list<string>
     * }
     */
    private function compose(Page $page): array
    {
        $errors = [];
        $normalizations = [];
        $metadata = $page->metadata ?? [];

        if (! is_array($metadata)) {
            $errors['metadata'][] = 'Legacy page metadata must be a JSON object.';
            $metadata = [];
        }

        foreach (array_diff(array_keys($metadata), self::METADATA_FIELDS) as $unknownField) {
            $errors["metadata.{$unknownField}"][] = 'This legacy metadata key is not represented by the page contract.';
        }

        if (! is_string($page->title)) {
            $errors['page.title'][] = 'Legacy page title must be a string.';
        }

        if (! is_string($page->slug)) {
            $errors['page.slug'][] = 'Legacy page slug must be a string.';
        }

        $description = $this->legacyText($metadata, 'description', '', $normalizations, $errors);
        $template = $this->legacyText($metadata, 'template', 'standard', $normalizations, $errors);
        $seoTitle = $this->legacyText($metadata, 'seoTitle', '', $normalizations, $errors);
        $seoDescription = $this->legacyText($metadata, 'seoDescription', '', $normalizations, $errors);
        $heroImage = $metadata['heroImage'] ?? null;

        if (! is_string($heroImage) && $heroImage !== null) {
            $errors['metadata.heroImage'][] = 'Legacy hero image must be a string or null.';
        }

        $sections = [];
        $sourceSections = [];
        $sourceSectionIds = [];
        $sourceSectionTypes = [];
        $sourceSectionKeySets = [];
        $keyLossPaths = [];

        foreach ($page->sections as $index => $legacySection) {
            $content = $legacySection->content;

            if (! is_array($content) || ($content !== [] && array_is_list($content))) {
                $errors["sections.{$index}.content"][] = 'Legacy section content must be a JSON object.';

                continue;
            }

            $stableId = $this->stableSectionId($page, $legacySection);

            if (array_key_exists('id', $content) && $content['id'] !== $stableId) {
                $errors["sections.{$index}.id"][] = 'Legacy content id conflicts with the deterministic section id.';
            }

            if (array_key_exists('type', $content) && $content['type'] !== $legacySection->type) {
                $errors["sections.{$index}.type"][] = 'Legacy content type conflicts with the section row type.';
            }

            $section = array_merge($content, [
                'id' => $stableId,
                'type' => $legacySection->type,
            ]);
            $preservedContent = array_diff_key($section, array_flip(['id', 'type']));
            $sourceContent = array_diff_key($content, array_flip(['id', 'type']));
            $this->collectMissingKeyPaths(
                $sourceContent,
                $preservedContent,
                "composed.sections.{$index}",
                $keyLossPaths,
            );

            $itemKeySets = [];
            if (isset($content['items']) && is_array($content['items'])) {
                foreach ($content['items'] as $item) {
                    $itemKeySets[] = is_array($item) ? array_keys($item) : [];
                }
            }

            $sections[] = $section;
            $sourceSectionIds[] = $stableId;
            $sourceSectionTypes[] = $legacySection->type;
            $sourceSectionKeySets[] = [
                'id' => $stableId,
                'content_keys' => array_keys($content),
                'output_keys' => array_keys($section),
                'item_key_sets' => $itemKeySets,
            ];
            $sourceSections[] = [
                'id' => (int) $legacySection->getKey(),
                'position' => (int) $legacySection->position,
                'type' => $legacySection->type,
                'content' => $content,
            ];
        }

        foreach (array_values(array_unique($keyLossPaths)) as $keyLossPath) {
            $errors[$keyLossPath][] = 'A legacy key was lost during document composition.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $document = [
            'title' => $page->title,
            'slug' => $page->slug,
            'description' => $description,
            'template' => $template,
            'heroImage' => $heroImage,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'sections' => $sections,
        ];
        $this->contract->validate($document);
        $document = $this->documents->canonicalizeDocument($document);

        $sourceSnapshot = [
            'page' => [
                'id' => (int) $page->getKey(),
                'slug' => $page->slug,
                'title' => $page->title,
                'metadata' => $page->metadata,
                'is_published' => (bool) $page->is_published,
            ],
            'sections' => $sourceSections,
        ];

        return [
            'document' => $document,
            'document_hash' => PageDocumentHasher::hash($document),
            'source_hash' => PageDocumentHasher::hash($sourceSnapshot),
            'source_section_ids' => $sourceSectionIds,
            'source_section_types' => $sourceSectionTypes,
            'source_section_key_sets' => $sourceSectionKeySets,
            'key_loss_paths' => array_values(array_unique($keyLossPaths)),
            'published_expected' => (bool) $page->is_published,
            'normalizations' => $normalizations,
        ];
    }

    /**
     * @param  array<string, mixed>  $composition
     * @param  Collection<int, PageDocument>  $documents
     */
    private function resultFor(Page $page, array $composition, Collection $documents): MigrationResult
    {
        $errors = [];
        $keyLossPaths = $composition['key_loss_paths'];
        $draft = $documents->firstWhere('stage', PageDocument::STAGE_DRAFT);
        $published = $documents->firstWhere('stage', PageDocument::STAGE_PUBLISHED);
        $expectedStatus = $composition['published_expected'] ? 'published' : 'draft';
        $statusMatches = $page->status === $expectedStatus;
        $canonicalPresent = $documents->isNotEmpty();

        if ($canonicalPresent) {
            if (! $draft instanceof PageDocument) {
                $errors[] = 'The canonical draft document is missing.';
            } else {
                $this->verifyStoredDocument('draft', $draft, $composition, $errors, $keyLossPaths);
            }

            if ($composition['published_expected']) {
                if (! $published instanceof PageDocument) {
                    $errors[] = 'The canonical published document is missing.';
                } else {
                    $this->verifyStoredDocument('published', $published, $composition, $errors, $keyLossPaths);
                }
            } elseif ($published instanceof PageDocument) {
                $errors[] = 'An unpublished legacy page has an unexpected published document.';
            }

            $expectedDocumentCount = $composition['published_expected'] ? 2 : 1;
            if ($documents->count() !== $expectedDocumentCount) {
                $errors[] = "Expected {$expectedDocumentCount} canonical document(s), found {$documents->count()}.";
            }

            if (! $statusMatches) {
                $errors[] = "Page status [{$page->status}] does not match legacy status [{$expectedStatus}].";
            }
        }

        $keyLossPaths = array_values(array_unique($keyLossPaths));

        $canonicalVerified = $canonicalPresent && $errors === [];

        return new MigrationResult(
            pageId: (int) $page->getKey(),
            slug: $composition['document']['slug'],
            sourceSectionCount: count($composition['source_section_ids']),
            sourceSectionIds: $composition['source_section_ids'],
            sourceSectionTypes: $composition['source_section_types'],
            sourceSectionKeySets: $composition['source_section_key_sets'],
            sourceHash: $composition['source_hash'],
            documentHash: $composition['document_hash'],
            publishedExpected: $composition['published_expected'],
            draftExists: $draft instanceof PageDocument,
            publishedExists: $published instanceof PageDocument,
            statusMatches: $statusMatches,
            canonicalVerified: $canonicalVerified,
            verified: ! $canonicalPresent || $canonicalVerified,
            keyLossPaths: $keyLossPaths,
            keyLossCount: count($keyLossPaths),
            normalizations: $composition['normalizations'],
            errors: $errors,
        );
    }

    /**
     * @param  array<string, mixed>  $composition
     * @param  list<string>  $errors
     * @param  list<string>  $keyLossPaths
     */
    private function verifyStoredDocument(
        string $stage,
        PageDocument $stored,
        array $composition,
        array &$errors,
        array &$keyLossPaths,
    ): void {
        $prefix = ucfirst($stage).' document';
        $document = $stored->document;

        if (! is_array($document)) {
            $errors[] = "{$prefix} JSON is not an object.";

            return;
        }

        if ($stored->slug !== $composition['document']['slug']) {
            $errors[] = "{$prefix} slug does not match the legacy slug.";
        }

        if ($stored->schema_version !== PageDocumentContract::VERSION) {
            $errors[] = "{$prefix} schema version is not ".PageDocumentContract::VERSION.'.';
        }

        if ($stored->lock_version !== 1) {
            $errors[] = "{$prefix} lock version is not the deterministic initial version.";
        }

        $storedDocumentHash = PageDocumentHasher::hash($document);
        if ($stored->content_hash !== $storedDocumentHash) {
            $errors[] = "{$prefix} content hash does not match its stored JSON.";
        }

        if ($storedDocumentHash !== $composition['document_hash']) {
            $errors[] = "{$prefix} content hash does not match the composed legacy document.";
        }

        $storedSections = $document['sections'] ?? null;
        if (! is_array($storedSections) || ! array_is_list($storedSections)) {
            $errors[] = "{$prefix} sections are not an ordered list.";

            return;
        }

        if (count($storedSections) !== count($composition['source_section_ids'])) {
            $errors[] = "{$prefix} section count does not match the legacy source.";
        }

        if (array_column($storedSections, 'id') !== $composition['source_section_ids']) {
            $errors[] = "{$prefix} section ids or order do not match the legacy source.";
        }

        if (array_column($storedSections, 'type') !== $composition['source_section_types']) {
            $errors[] = "{$prefix} section types or order do not match the legacy source.";
        }

        $keyLossOffset = count($keyLossPaths);
        $this->collectMissingKeyPaths(
            $composition['document']['sections'],
            $storedSections,
            "{$stage}.sections",
            $keyLossPaths,
        );

        foreach (array_slice($keyLossPaths, $keyLossOffset) as $keyLossPath) {
            $errors[] = "Key loss at [{$keyLossPath}].";
        }

        foreach ($composition['source_section_key_sets'] as $index => $sourceKeySet) {
            if (! isset($storedSections[$index]) || ! is_array($storedSections[$index])) {
                continue;
            }

            $storedKeys = array_keys($storedSections[$index]);
            sort($storedKeys, SORT_STRING);
            $expectedKeys = $sourceKeySet['output_keys'];
            sort($expectedKeys, SORT_STRING);

            if ($storedKeys !== $expectedKeys) {
                $errors[] = "{$stage}.sections.{$index} key set does not match the legacy source.";
            }

            $expectedItems = $composition['document']['sections'][$index]['items'] ?? null;
            $storedItems = $storedSections[$index]['items'] ?? null;

            if ($expectedItems === null) {
                continue;
            }

            if (! is_array($storedItems) || ! array_is_list($storedItems)) {
                $errors[] = "{$stage}.sections.{$index}.items is not an ordered list.";

                continue;
            }

            if (count($storedItems) !== count($expectedItems)) {
                $errors[] = "{$stage}.sections.{$index}.items count does not match the legacy source.";
            }

            $expectedItemIds = array_map(
                static fn (mixed $item): mixed => is_array($item) ? ($item['id'] ?? null) : null,
                $expectedItems,
            );
            $storedItemIds = array_map(
                static fn (mixed $item): mixed => is_array($item) ? ($item['id'] ?? null) : null,
                $storedItems,
            );

            if ($storedItemIds !== $expectedItemIds) {
                $errors[] = "{$stage}.sections.{$index}.items item ids or order do not match the legacy source.";
            }

            foreach ($sourceKeySet['item_key_sets'] as $itemIndex => $expectedItemKeys) {
                if (! isset($storedItems[$itemIndex]) || ! is_array($storedItems[$itemIndex])) {
                    continue;
                }

                $storedItemKeys = array_keys($storedItems[$itemIndex]);
                sort($storedItemKeys, SORT_STRING);
                sort($expectedItemKeys, SORT_STRING);

                if ($storedItemKeys !== $expectedItemKeys) {
                    $errors[] = "{$stage}.sections.{$index}.items.{$itemIndex} key set does not match the legacy source.";
                }
            }
        }
    }

    /**
     * Derive missing-key paths recursively while preserving list order.
     *
     * @param  list<string>  $keyLossPaths
     */
    private function collectMissingKeyPaths(
        mixed $expected,
        mixed $actual,
        string $path,
        array &$keyLossPaths,
    ): void {
        if (! is_array($expected)) {
            return;
        }

        if (array_is_list($expected)) {
            if (! is_array($actual) || ! array_is_list($actual)) {
                return;
            }

            foreach ($expected as $index => $expectedValue) {
                $itemPath = "{$path}.{$index}";

                if (! array_key_exists($index, $actual)) {
                    $keyLossPaths[] = $itemPath;

                    continue;
                }

                $this->collectMissingKeyPaths($expectedValue, $actual[$index], $itemPath, $keyLossPaths);
            }

            return;
        }

        if (! is_array($actual) || array_is_list($actual)) {
            return;
        }

        foreach ($expected as $key => $expectedValue) {
            $keyPath = "{$path}.{$key}";

            if (! array_key_exists($key, $actual)) {
                $keyLossPaths[] = $keyPath;

                continue;
            }

            $this->collectMissingKeyPaths($expectedValue, $actual[$key], $keyPath, $keyLossPaths);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $normalizations
     * @param  array<string, list<string>>  $errors
     */
    private function legacyText(
        array $metadata,
        string $field,
        string $default,
        array &$normalizations,
        array &$errors,
    ): string {
        if (! array_key_exists($field, $metadata)) {
            $normalizations[] = "metadata.{$field}:missing_to_default";

            return $default;
        }

        if ($metadata[$field] === null) {
            $normalizations[] = "metadata.{$field}:null_to_default";

            return $default;
        }

        if (! is_string($metadata[$field])) {
            $errors["metadata.{$field}"][] = 'Legacy text metadata must be a string or null.';

            return $default;
        }

        return $metadata[$field];
    }

    private function stableSectionId(Page $page, PageSection $section): string
    {
        return "legacy-page-{$page->getKey()}-section-{$section->getKey()}";
    }
}
