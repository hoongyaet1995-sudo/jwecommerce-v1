<?php
namespace Tests\Unit;

use App\Services\Marketplace\ShopeePublishingVariants;
use PHPUnit\Framework\TestCase;

class ShopeePublishingVariantsTest extends TestCase
{
    public static function invalidReviews(): array
    {
        $model=['tier_index'=>[0],'model_sku'=>'RED','original_price'=>10,'seller_stock'=>[['stock'=>0]]];
        $payload=['jw_variations'=>['tier_variation'=>[['name'=>'Color','option_list'=>[['option'=>'Red']]]],'model'=>[$model]]];
        $source=['variants'=>[['sku'=>'RED','price_minor'=>1000]]];
        $cases=[];
        $cases[]=[[],['variants'=>[['sku'=>'RED'],['sku'=>'BLUE']]]];
        $changed=$payload; $changed['jw_variations']['model'][0]['normal_stock']=10; $cases[]=[$changed,$source];
        $changed=$payload; $changed['jw_variations']['model'][0]['seller_stock'][0]['stock']=1; $cases[]=[$changed,$source];
        $changed=$payload; $changed['jw_variations']['model'][0]['tier_index']=[1]; $cases[]=[$changed,$source];
        $changed=$payload; $changed['jw_variations']['model'][]=$model; $cases[]=[$changed,$source];
        $changed=$payload; $changed['jw_variations']['model'][0]['model_sku']='FOREIGN'; $cases[]=[$changed,$source];
        $changed=$payload; $changed['jw_variations']['model'][0]['original_price']=11; $cases[]=[$changed,$source];
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidReviews')]
    public function test_incomplete_or_changed_reviews_are_rejected_before_parent_creation(array $payload,array $source): void
    {
        $this->expectException(\DomainException::class);
        ShopeePublishingVariants::validate($payload,$source);
    }
}
