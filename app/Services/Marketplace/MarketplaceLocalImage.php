<?php

namespace App\Services\Marketplace;

use App\Domain\Cms\MediaUrlPolicy;
use App\Models\ProductImage;
use DomainException;
use Illuminate\Support\Facades\Storage;

final class MarketplaceLocalImage
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** Read an owned local asset only; remote retrieval and image decoding are separate steps. */
    public function read(int $productId, int $imageId): string
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);
        $path = (string) $image->path;

        if (MediaUrlPolicy::isManaged($path) && preg_match('/\.(jpg|png|webp|gif)$/D', $path)) {
            $root = Storage::disk('public')->path('cms/media');
            $candidate = Storage::disk('public')->path(substr($path, strlen('/storage/')));
        } elseif (preg_match('#\A/images/catalog/[a-z0-9][a-z0-9_-]*\.(jpg|jpeg|png|webp|gif)\z#D', $path)) {
            $root = public_path('images/catalog');
            $candidate = public_path(ltrim($path, '/'));
        } else {
            throw new DomainException('This image is not a supported local catalog asset.');
        }

        $resolvedRoot = realpath($root);
        $resolvedFile = realpath($candidate);
        if ($resolvedRoot === false || $resolvedFile === false
            || ! str_starts_with($resolvedFile, $resolvedRoot.DIRECTORY_SEPARATOR)
            || ! is_file($resolvedFile) || ! is_readable($resolvedFile)) {
            throw new DomainException('The catalog image is unavailable.');
        }

        $bytes = @file_get_contents($resolvedFile, false, null, 0, self::MAX_BYTES + 1);
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new DomainException('The catalog image must be non-empty and at most 10 MB.');
        }

        return $bytes;
    }
}
