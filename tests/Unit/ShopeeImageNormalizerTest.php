<?php

namespace Tests\Unit;

use App\Services\Marketplace\ShopeeImageNormalizer;
use PHPUnit\Framework\TestCase;

class ShopeeImageNormalizerTest extends TestCase
{
    public function test_gif_is_converted_to_a_shopee_jpeg(): void
    {
        if (! function_exists('imagegif')) {
            $this->markTestSkipped('GD GIF support is required for image normalization.');
        }
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 80, 120));
        ob_start();
        imagegif($image);
        $gif = ob_get_clean();
        imagedestroy($image);

        $normalized = (new ShopeeImageNormalizer)->normalize($gif);
        $info = getimagesizefromstring($normalized);

        $this->assertSame('image/jpeg', $info['mime'] ?? null);
        $this->assertLessThanOrEqual(2 * 1024 * 1024, strlen($normalized));
    }
}
