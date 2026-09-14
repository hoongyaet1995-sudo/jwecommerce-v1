<?php
namespace Tests\Unit;
use App\Services\Marketplace\ShopeeVariantImagePlan;
use PHPUnit\Framework\TestCase;
class ShopeeVariantImagePlanTest extends TestCase
{
    public function test_groups_images_by_first_option_and_rejects_conflicting_images(): void
    {
        $source=['options'=>[['name'=>'Color','values'=>['Red']],['name'=>'Size','values'=>['S','M']]],'variants'=>[
            ['id'=>1,'sku'=>'R-S','price_minor'=>100,'options'=>['Color'=>'Red','Size'=>'S'],'image_url'=>'/red.png'],
            ['id'=>2,'sku'=>'R-M','price_minor'=>100,'options'=>['Color'=>'Red','Size'=>'M'],'image_url'=>'/red.png']]];
        $this->assertSame([0=>['variant_id'=>1,'path'=>'/red.png']],ShopeeVariantImagePlan::fromSource($source));
        $source['variants'][1]['image_url']='/different.png';
        $this->expectException(\DomainException::class);
        ShopeeVariantImagePlan::fromSource($source);
    }
}
