<?php

namespace App\Services\Marketplace;

use App\Domain\Catalog\CatalogMediaReferenceSynchronizer;
use App\Models\CmsMediaAsset;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ManagedMediaIngest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class MarketplaceAttributeImageUpload
{
    public function __construct(
        private readonly ManagedMediaIngest $ingest,
        private readonly CatalogMediaReferenceSynchronizer $catalogMedia,
    ) {}

    public function store(Product $product, UploadedFile $file, int $actorId): ProductImage
    {
        $asset = null;
        try {
            return DB::transaction(function () use ($product, $file, $actorId, &$asset): ProductImage {
                $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
                $asset = $this->ingest->ingest($file, $actorId);
                $position = ((int) $locked->images()->max('position')) + ($locked->images()->exists() ? 1 : 0);
                $image = $locked->images()->create([
                    'path' => $asset->url,
                    'alt' => $asset->original_name,
                    'position' => $position,
                ]);
                $this->catalogMedia->syncProduct($locked);

                return $image;
            });
        } catch (Throwable $error) {
            if ($asset instanceof CmsMediaAsset) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
            throw $error;
        }
    }
}
