<?php
namespace Tests\Unit;
use App\Services\Marketplace\ShopeeListingAttributes;
use PHPUnit\Framework\TestCase;
class ShopeeListingAttributesTest extends TestCase
{
    public function test_normalization_applies_conditional_presence_and_value_rules_together(): void
    {
        $child=['attribute_id'=>20,'mandatory'=>true,'attribute_info'=>['input_type'=>3,'input_validation_type'=>1,'format_type'=>2,'attribute_unit_list'=>['g'],'max_value_count'=>1],'attribute_value_list'=>[]];
        $tree=[['attribute_id'=>10,'mandatory'=>true,'attribute_info'=>['input_type'=>1,'input_validation_type'=>2,'format_type'=>1,'max_value_count'=>1],
            'attribute_value_list'=>[['value_id'=>7,'name'=>'Metal','child_attribute_list'=>[$child]]]]];
        $parent=['attribute_id'=>10,'attribute_value_list'=>[['value_id'=>7,'original_value_name'=>'Forged']]];
        $weight=['attribute_id'=>20,'attribute_value_list'=>[['value_id'=>0,'original_value_name'=>'100','value_unit'=>'g']]];
        $result=ShopeeListingAttributes::normalize($tree,[$weight,$parent]);
        $this->assertSame(10,$result[0]['attribute_id']);
        $this->assertSame('Metal',$result[0]['attribute_value_list'][0]['original_value_name']);
        $this->assertSame('100',$result[1]['attribute_value_list'][0]['original_value_name']);
        foreach ([[$parent],[$parent,array_replace($weight,['attribute_value_list'=>[['value_id'=>0,'original_value_name'=>'heavy','value_unit'=>'g']]])]] as $invalid) {
            try {ShopeeListingAttributes::normalize($tree,$invalid);$this->fail('Incomplete or invalid attributes accepted.');}
            catch (\DomainException $e) {$this->assertNotEmpty($e->getMessage());}
        }
    }
}
