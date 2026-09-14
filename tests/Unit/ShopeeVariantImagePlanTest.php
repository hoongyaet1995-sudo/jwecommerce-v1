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
    public function test_explicit_choice_resolves_conflict_without_changing_source(): void
    {
        $source=['options'=>[['name'=>'Color','values'=>['Black']],['name'=>'Size','values'=>['S','M']]],'variants'=>[
            ['id'=>1,'sku'=>'B-S','price_minor'=>100,'options'=>['Color'=>'Black','Size'=>'S'],'image_url'=>'/one.png'],
            ['id'=>2,'sku'=>'B-M','price_minor'=>100,'options'=>['Color'=>'Black','Size'=>'M'],'image_url'=>'/two.png']]];
        $before=$source;
        $this->assertSame([['variant_id'=>2,'path'=>'/two.png']],ShopeeVariantImagePlan::fromSource($source,['Black'=>2]));
        $this->assertSame($before,$source);
        $this->expectException(\DomainException::class);
        ShopeeVariantImagePlan::fromSource($source,['Black'=>999]);
    }
}
