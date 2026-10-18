<?php

namespace App\Domain\Media;

use App\Models\CatalogMediaReference;
use App\Models\CmsMediaAsset;
use App\Models\CmsMediaReference;
use Illuminate\Database\Eloquent\Collection;

final class ManagedMediaOrphanState
{
    /** @param Collection<int, CmsMediaAsset> $assets */
    public function refresh(Collection $assets): void
    {
        if ($assets->isEmpty()) {
            return;
        }

        $assetIds = $assets->modelKeys();
        $cmsReferenceIds = CmsMediaReference::query()
            ->whereIn('asset_id', $assetIds)
            ->distinct()
            ->pluck('asset_id');
        $catalogReferenceIds = CatalogMediaReference::query()
            ->whereIn('asset_id', $assetIds)
            ->distinct()
            ->pluck('asset_id');
        $revisionIds = \Illuminate\Support\Facades\DB::table('page_revision_media')->whereIn('asset_id', $assetIds)->distinct()->pluck('asset_id');
        $referencedIds = $cmsReferenceIds
            ->merge($revisionIds)
            ->merge($catalogReferenceIds)
            ->mapWithKeys(static fn (string $id): array => [$id => true]);

        foreach ($assets as $asset) {
            $isReferenced = $referencedIds->has((string) $asset->getKey());

            if ($isReferenced && $asset->orphaned_at !== null) {
                $asset->forceFill(['orphaned_at' => null])->saveOrFail();
            } elseif (! $isReferenced && $asset->orphaned_at === null) {
                $asset->forceFill(['orphaned_at' => now()])->saveOrFail();
            }
        }
    }
}
