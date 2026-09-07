<?php
namespace Tests\Unit;
use App\Services\Marketplace\ShopeePackageLimits;
use PHPUnit\Framework\TestCase;
class ShopeePackageLimitsTest extends TestCase
{
    public function test_package_obeys_real_channel_weight_and_centimetre_limits(): void
    {
        $channel=['weight_limit'=>['item_min_weight'=>0,'item_max_weight'=>20],
            'item_max_dimension'=>['height'=>30,'width'=>30,'length'=>30,'unit'=>'cm','dimension_sum'=>0]];
        ShopeePackageLimits::validate($channel,20,['height'=>30,'width'=>30,'length'=>30]);
        foreach ([[21,['height'=>30,'width'=>30,'length'=>30]],[20,['height'=>31,'width'=>30,'length'=>30]]] as [$weight,$dimensions]) {
            try {ShopeePackageLimits::validate($channel,$weight,$dimensions);$this->fail('Oversized package accepted.');}
            catch (\DomainException $e) {$this->assertNotEmpty($e->getMessage());}
        }
    }
    public function test_dimension_sum_is_checked_even_when_each_axis_fits(): void
    {
        $this->expectException(\DomainException::class);
        ShopeePackageLimits::validate(['weight_limit'=>['item_min_weight'=>null,'item_max_weight'=>0],
            'item_max_dimension'=>['height'=>30,'width'=>30,'length'=>30,'unit'=>'cm','dimension_sum'=>60]],1,
            ['height'=>25,'width'=>25,'length'=>25]);
    }
}
