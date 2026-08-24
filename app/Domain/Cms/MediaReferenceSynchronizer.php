<?php

namespace App\Domain\Cms;

use App\Domain\Media\ManagedMediaOrphanState;
use App\Models\CmsMediaAsset;
use App\Models\CmsMediaReference;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class MediaReferenceSynchronizer
{
    /** @var list<string> */
    private const SECTION_MEDIA_FIELDS = [
        'mediaUrl',
        'authorAvatar',
        'videoEmbedUrl',
        'beforeImageUrl',
        'afterImageUrl',
    ];

    /** @var list<string> */
    private const ITEM_MEDIA_FIELDS = [
        'imageUrl',
        'videoUrl',
        'authorAvatar',
    ];

    public function sync(PageDocument $document): void
    {
        $this->assertTransaction();
        $desired = $this->managedReferences($document->document);
        $urls = array_values(array_unique(array_values($desired)));
        $desiredCandidates = $urls === []
            ? collect()
            : CmsMediaAsset::query()->whereIn('url', $urls)->get(['id', 'url'])->keyBy('url');
        $currentSnapshot = CmsMediaReference::query()
            ->where('page_document_id', $document->getKey())
            ->get(['asset_id']);
        $assetIds = array_merge(
            $currentSnapshot->pluck('asset_id')->map('strval')->all(),
            $desiredCandidates->pluck('id')->map('strval')->all(),
        );
        $lockedAssets = $this->lockAssets($assetIds);
        $assetsByUrl = $lockedAssets->keyBy('url');
        $errors = [];

        foreach ($desired as $path => $url) {
            if (! $assetsByUrl->has($url)) {
                $errors[$path][] = 'The managed media asset does not exist.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $current = CmsMediaReference::query()
            ->where('page_document_id', $document->getKey())
            ->lockForUpdate()
            ->get()
            ->keyBy('field_path');

        $this->assertReferencesCoveredByLocks($current->pluck('asset_id')->map('strval')->all(), $lockedAssets);

        foreach ($desired as $path => $url) {
            /** @var CmsMediaAsset $asset */
            $asset = $assetsByUrl->get($url);
            $reference = $current->pull($path);

            if ($reference instanceof CmsMediaReference) {
                if ($reference->asset_id !== $asset->getKey()) {
                    $reference->forceFill(['asset_id' => $asset->getKey()])->saveOrFail();
                }

                continue;
            }

            CmsMediaReference::query()->create([
                'asset_id' => $asset->getKey(),
                'page_document_id' => $document->getKey(),
                'field_path' => $path,
            ]);
        }

        if ($current->isNotEmpty()) {
            CmsMediaReference::query()->whereKey($current->pluck('id'))->delete();
        }

        $this->refreshOrphanState($lockedAssets);
    }

    public function removeReferencesForPage(Page $page): void
    {
        $this->assertTransaction();
        $documentIds = PageDocument::query()
            ->where('page_id', $page->getKey())
            ->lockForUpdate()
            ->pluck('id');

        if ($documentIds->isEmpty()) {
            return;
        }

        $referenceSnapshot = CmsMediaReference::query()
            ->whereIn('page_document_id', $documentIds)
            ->get(['asset_id']);
        $lockedAssets = $this->lockAssets($referenceSnapshot->pluck('asset_id')->map('strval')->all());
        $references = CmsMediaReference::query()
            ->whereIn('page_document_id', $documentIds)
            ->lockForUpdate()
            ->get();

        $this->assertReferencesCoveredByLocks($references->pluck('asset_id')->map('strval')->all(), $lockedAssets);

        if ($references->isNotEmpty()) {
            CmsMediaReference::query()->whereKey($references->pluck('id'))->delete();
        }

        $this->refreshOrphanState($lockedAssets);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, string>
     */
    private function managedReferences(array $document): array
    {
        $references = [];
        $this->capture($references, 'heroImage', $document['heroImage'] ?? null);

        foreach ($document['sections'] ?? [] as $sectionIndex => $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach (self::SECTION_MEDIA_FIELDS as $field) {
                if (array_key_exists($field, $section)) {
                    $this->capture(
                        $references,
                        "sections.{$sectionIndex}.{$field}",
                        $section[$field],
                    );
                }
            }

            foreach ($section['items'] ?? [] as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach (self::ITEM_MEDIA_FIELDS as $field) {
                    if (array_key_exists($field, $item)) {
                        $this->capture(
                            $references,
                            "sections.{$sectionIndex}.items.{$itemIndex}.{$field}",
                            $item[$field],
                        );
                    }
                }
            }
        }

        return $references;
    }

    /** @param array<string, string> $references */
    private function capture(array &$references, string $path, mixed $value): void
    {
        if (is_string($value) && MediaUrlPolicy::isManaged($value)) {
            $references[$path] = $value;
        }
    }

    /** @param Collection<int, CmsMediaAsset> $assets */
    private function refreshOrphanState(Collection $assets): void
    {
        app(ManagedMediaOrphanState::class)->refresh($assets);
    }

    /**
     * @param  list<string>  $assetIds
     * @return Collection<int, CmsMediaAsset>
     */
    private function lockAssets(array $assetIds): Collection
    {
        $assetIds = array_values(array_unique(array_map('strval', $assetIds)));
        sort($assetIds, SORT_STRING);

        if ($assetIds === []) {
            return new Collection;
        }

        return CmsMediaAsset::query()
            ->whereKey($assetIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  list<string>  $assetIds
     * @param  Collection<int, CmsMediaAsset>  $lockedAssets
     */
    private function assertReferencesCoveredByLocks(array $assetIds, Collection $lockedAssets): void
    {
        $lockedIds = $lockedAssets->keyBy(static fn (CmsMediaAsset $asset): string => (string) $asset->getKey());

        foreach ($assetIds as $assetId) {
            if (! $lockedIds->has($assetId)) {
                throw new RuntimeException('A media reference changed outside the locked page document transaction.');
            }
        }
    }

    private function assertTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new RuntimeException(
                'Media reference synchronization requires an active database transaction.',
            );
        }
    }
}
