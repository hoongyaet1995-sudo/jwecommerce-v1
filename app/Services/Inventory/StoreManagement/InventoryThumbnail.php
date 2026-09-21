<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class InventoryThumbnail
{
    public function url(int $variantId, ?string $variantImage, ?string $optionImage, ?string $productImage): ?string
    {
        $source = collect([$optionImage, $variantImage, $productImage])
            ->first(fn ($value): bool => is_string($value) && trim($value) !== '');

        if (! is_string($source)) {
            return null;
        }

        if (! str_starts_with($source, '/images/') && ! str_starts_with($source, '/storage/')) {
            return $source;
        }

        return '/admin/inventory/thumbnails/'.$variantId.'?v='.substr(hash('sha256', $source), 0, 12);
    }

    public function source(int $variantId): ?string
    {
        $variant = DB::table('product_variants')->where('id', $variantId)
            ->first(['id', 'product_id', 'image_url', 'option_image']);

        if (! $variant) {
            return null;
        }

        $productImage = DB::table('product_images')
            ->where('product_id', $variant->product_id)
            ->where(fn ($query) => $query->whereNull('product_variant_id')->orWhere('product_variant_id', $variantId))
            ->orderByRaw('CASE WHEN product_variant_id = ? THEN 0 ELSE 1 END', [$variantId])
            ->orderBy('position')
            ->value('path');

        return collect([$variant->option_image, $variant->image_url, $productImage])
            ->first(fn ($value): bool => is_string($value) && trim($value) !== '');
    }

    public function localPath(string $source): ?string
    {
        $relative = match (true) {
            str_starts_with($source, '/images/') => ltrim($source, '/'),
            str_starts_with($source, '/storage/') => 'storage/'.substr($source, strlen('/storage/')),
            default => null,
        };

        if ($relative === null || str_contains(rawurldecode($relative), '..')) {
            return null;
        }

        $path = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $real = realpath($path);
        $public = realpath(public_path());

        return $real !== false && $public !== false && str_starts_with($real, $public.DIRECTORY_SEPARATOR) ? $real : null;
    }
}
