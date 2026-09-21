<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\InventoryThumbnail;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class InventoryThumbnailController extends Controller
{
    public function __invoke(int $variant, InventoryThumbnail $thumbnail): BinaryFileResponse|RedirectResponse
    {
        $source = $thumbnail->source($variant);
        abort_unless($source, 404);

        $original = $thumbnail->localPath($source);
        if ($original === null || ! extension_loaded('gd')) {
            return redirect()->to($source)->withHeaders(['Cache-Control' => 'private, max-age=86400']);
        }

        $cacheDirectory = storage_path('app/inventory-thumbnails');
        if (! is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0755, true);
        }
        $target = $cacheDirectory.DIRECTORY_SEPARATOR.hash('sha256', $source.'|'.filemtime($original)).'.webp';
        if (! is_file($target)) {
            $this->create($original, $target);
        }

        return response()->file($target, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=604800, immutable',
        ]);
    }

    private function create(string $source, string $target): void
    {
        $contents = file_get_contents($source);
        $image = $contents === false ? false : imagecreatefromstring($contents);
        abort_unless($image !== false, 415);

        $width = imagesx($image);
        $height = imagesy($image);
        $size = 160;
        $scale = min($size / max(1, $width), $size / max(1, $height), 1);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagewebp($canvas, $target, 72);
        imagedestroy($canvas);
        imagedestroy($image);
    }
}
