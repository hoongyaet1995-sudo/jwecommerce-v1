<?php

namespace App\Domain\Catalog;

use App\Domain\Cms\MediaUrlPolicy;
use App\Domain\Media\ManagedMediaOrphanState;
use App\Models\CatalogMediaReference;
use App\Models\Category;
use App\Models\CmsMediaAsset;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CatalogMediaReferenceSynchronizer
{
    private const CATEGORY = 'category';

    private const COLLECTION = 'collection';

    private const PRODUCT = 'product';

    public function __construct(private readonly ManagedMediaOrphanState $orphanState) {}

    public function syncCategory(Category $category): void
    {
        $this->sync(self::CATEGORY, $category->getKey(), [
            'image' => $category->image_url,
        ]);
    }

    public function syncCollection(Collection $collection): void
    {
        $this->sync(self::COLLECTION, $collection->getKey(), [
            'coverImage' => $collection->cover_image,
        ]);
    }

    public function syncProduct(Product $product): void
    {
        $desired = $product->images()
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'path', 'position'])
            ->mapWithKeys(static fn ($image): array => ["images.{$image->position}" => $image->path])
            ->all();

        $this->sync(self::PRODUCT, $product->getKey(), $desired);
    }

    public function removeCategory(Category $category): void
    {
        $this->remove(self::CATEGORY, $category->getKey());
    }

    public function removeCollection(Collection $collection): void
    {
        $this->remove(self::COLLECTION, $collection->getKey());
    }

    public function removeProduct(Product $product): void
    {
        $this->remove(self::PRODUCT, $product->getKey());
    }

    /** @param array<string, mixed> $fields */
    private function sync(string $ownerType, int|string $ownerId, array $fields): void
    {
        $this->assertTransaction();
        $desired = collect($fields)
            ->filter(static fn (mixed $url): bool => is_string($url) && MediaUrlPolicy::isManaged($url))
            ->map(static fn (string $url): string => $url)
            ->all();
        $currentSnapshot = CatalogMediaReference::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->get(['asset_id']);
        $candidateAssets = $desired === []
            ? collect()
            : CmsMediaAsset::query()->whereIn('url', array_values($desired))->get(['id', 'url']);
        $lockedAssets = $this->lockAssets(array_merge(
            $currentSnapshot->pluck('asset_id')->map('strval')->all(),
            $candidateAssets->pluck('id')->map('strval')->all(),
        ));
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

        $current = CatalogMediaReference::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->lockForUpdate()
            ->get()
            ->keyBy('field_path');
        $this->assertReferencesCoveredByLocks($current->pluck('asset_id')->map('strval')->all(), $lockedAssets);

        foreach ($desired as $path => $url) {
            /** @var CmsMediaAsset $asset */
            $asset = $assetsByUrl->get($url);
            $reference = $current->pull($path);

            if ($reference instanceof CatalogMediaReference) {
                if ($reference->asset_id !== $asset->getKey()) {
                    $reference->forceFill(['asset_id' => $asset->getKey()])->saveOrFail();
                }

                continue;
            }

            CatalogMediaReference::query()->create([
                'asset_id' => $asset->getKey(),
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'field_path' => $path,
            ]);
        }

        if ($current->isNotEmpty()) {
            CatalogMediaReference::query()->whereKey($current->pluck('id'))->delete();
        }

        $this->orphanState->refresh($lockedAssets);
    }

    private function remove(string $ownerType, int|string $ownerId): void
    {
        $this->assertTransaction();
        $snapshot = CatalogMediaReference::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->get(['asset_id']);
        $lockedAssets = $this->lockAssets($snapshot->pluck('asset_id')->map('strval')->all());
        $references = CatalogMediaReference::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->lockForUpdate()
            ->get();
        $this->assertReferencesCoveredByLocks($references->pluck('asset_id')->map('strval')->all(), $lockedAssets);

        if ($references->isNotEmpty()) {
            CatalogMediaReference::query()->whereKey($references->pluck('id'))->delete();
        }

        $this->orphanState->refresh($lockedAssets);
    }

    /** @param list<string> $assetIds @return EloquentCollection<int, CmsMediaAsset> */
    private function lockAssets(array $assetIds): EloquentCollection
    {
        $assetIds = array_values(array_unique(array_map('strval', $assetIds)));
        sort($assetIds, SORT_STRING);

        if ($assetIds === []) {
            return new EloquentCollection;
        }

        return CmsMediaAsset::query()
            ->whereKey($assetIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param list<string> $assetIds @param EloquentCollection<int, CmsMediaAsset> $lockedAssets */
    private function assertReferencesCoveredByLocks(array $assetIds, EloquentCollection $lockedAssets): void
    {
        $lockedIds = $lockedAssets->keyBy(static fn (CmsMediaAsset $asset): string => (string) $asset->getKey());

        foreach ($assetIds as $assetId) {
            if (! $lockedIds->has($assetId)) {
                throw new RuntimeException('A Catalog media reference changed outside the locked transaction.');
            }
        }
    }

    private function assertTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new RuntimeException('Catalog media reference synchronization requires an active database transaction.');
        }
    }
}
