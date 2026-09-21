<?php

namespace App\Services\Marketplace;

use DomainException;

final class ShopeeImageNormalizer
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function normalize(string $bytes): string
    {
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? null;
        if (! is_string($mime)) {
            throw new DomainException('The selected product image is damaged. Replace the image and retry publishing.');
        }
        if (in_array($mime, ['image/jpeg', 'image/png'], true) && strlen($bytes) <= self::MAX_BYTES) {
            return $bytes;
        }
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new DomainException('Shopee accepts JPG or PNG product images. Replace this image and retry publishing.');
        }
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw new DomainException('JW could not prepare this image for Shopee. Upload a JPG or PNG under 2 MB and retry.');
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new DomainException('The selected product image could not be decoded. Replace the image and retry publishing.');
        }
        try {
            $width = imagesx($source);
            $height = imagesy($source);
            for ($attempt = 0; $attempt < 12; $attempt++) {
                $scale = pow(0.82, max(0, $attempt - 4));
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($height * $scale));
                $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($canvas === false) {
                    break;
                }
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $white);
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                ob_start();
                imagejpeg($canvas, null, max(45, 90 - ($attempt * 8)));
                $encoded = ob_get_clean();
                imagedestroy($canvas);
                if (is_string($encoded) && $encoded !== '' && strlen($encoded) <= self::MAX_BYTES) {
                    return $encoded;
                }
            }
        } finally {
            imagedestroy($source);
        }
        throw new DomainException('JW could not reduce this image below Shopee’s 2 MB limit. Replace it with a smaller JPG or PNG and retry.');
    }
}
