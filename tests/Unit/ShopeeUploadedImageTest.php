<?php

namespace Tests\Unit;

use App\Services\Marketplace\ShopeeUploadedImage;
use PHPUnit\Framework\TestCase;

class ShopeeUploadedImageTest extends TestCase
{
    public function test_successful_provider_list_accepts_explicit_null_error(): void
    {
        $this->assertSame('image-123', ShopeeUploadedImage::identity(['error'=>'','response'=>[
            'image_info'=>['image_id'=>'image-123'],
            'image_info_list'=>[['id'=>0,'error'=>null,'image_info'=>['image_id'=>'image-123']]],
        ]]));
    }

    public function test_single_image_success_returns_platform_identity(): void
    {
        $this->assertSame('image-123',ShopeeUploadedImage::identity(['error'=>'','response'=>[
            'image_info'=>['image_id'=>'image-123']]]));
    }

    public function test_partial_or_missing_image_result_never_counts_as_uploaded(): void
    {
        foreach ([['error'=>'bad'],['error'=>'','response'=>[]],
            ['error'=>'','response'=>['image_info'=>['image_id'=>'image-123'],
                'image_info_list'=>[['id'=>0,'error'=>'failed','image_info'=>['image_id'=>'image-123']]]]],
            ['error'=>'','response'=>['image_info'=>['image_id'=>'-']]]] as $payload) {
            try {ShopeeUploadedImage::identity($payload);$this->fail('Invalid upload must fail.');}
            catch (\RuntimeException $exception) {$this->assertNotEmpty($exception->getMessage());}
        }
    }
}
