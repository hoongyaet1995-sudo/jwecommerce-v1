<?php
namespace Tests\Unit;
use App\Services\Marketplace\ShopeeMasterVariants;
use PHPUnit\Framework\TestCase;

class ShopeeMasterVariantsTest extends TestCase
{
    private function source(): array
    {
        return ['options'=>[['name'=>'Color','values'=>['Red','Blue']],['name'=>'Size','values'=>['S','L']]],
            'variants'=>[['sku'=>'RED-S','price_minor'=>1250,'options'=>['Color'=>'Red','Size'=>'S']],
                ['sku'=>'BLUE-L','price_minor'=>1599,'options'=>['Color'=>'Blue','Size'=>'L']]]];
    }
    public function test_preserves_jw_option_order_and_sparse_combinations_without_inventing_variants(): void
    {
        $result=ShopeeMasterVariants::fromSource($this->source());
        $this->assertSame(['Color','Size'],array_column($result['tier_variation'],'name'));
        $this->assertSame([[0,0],[1,1]],array_column($result['model'],'tier_index'));
        $this->assertSame(['RED-S','BLUE-L'],array_column($result['model'],'model_sku'));
        $this->assertSame(15.99,$result['model'][1]['original_price']);
        $this->assertSame([['stock'=>0]],$result['model'][1]['seller_stock']);
    }
    public function test_duplicate_option_combinations_cannot_be_published_as_different_skus(): void
    {
        $source=$this->source();$source['variants'][1]['options']=$source['variants'][0]['options'];
        $this->expectException(\DomainException::class);
        ShopeeMasterVariants::fromSource($source);
    }
    public function test_master_binding_generates_variants_without_accepting_browser_model_overrides(): void
    {
        $source=$this->source()+['title'=>'JW title','description'=>'JW description'];
        $bound=\App\Services\Marketplace\ShopeePublishingMasterData::bind($source,['jw_variations'=>['model'=>[['model_sku'=>'FOREIGN']]]]);
        $this->assertSame(ShopeeMasterVariants::fromSource($source),$bound['jw_variations']);
        $this->assertSame('JW title',$bound['item_name']);
        $this->assertSame([['stock'=>0]],$bound['seller_stock']);
    }
    public function test_missing_source_option_values_are_not_replaced_with_variant_titles(): void
    {
        $source=$this->source();unset($source['variants'][1]['options']['Size']);
        $this->expectException(\DomainException::class);
        ShopeeMasterVariants::fromSource($source);
    }
}
