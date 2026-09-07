<?php

namespace Tests\Unit;

use App\Services\Marketplace\ShopeeAttributePresence;
use PHPUnit\Framework\TestCase;

class ShopeeAttributePresenceTest extends TestCase
{
    private function tree(): array
    {
        return [['attribute_id'=>10,'mandatory'=>true,'attribute_value_list'=>[
            ['value_id'=>1,'child_attribute_list'=>[['attribute_id'=>20,'mandatory'=>true,'attribute_value_list'=>[]]]],
            ['value_id'=>2,'child_attribute_list'=>[]],
        ]]];
    }

    public function test_selected_parent_activates_only_its_required_children(): void
    {
        $parent=['attribute_id'=>10,'attribute_value_list'=>[['value_id'=>1]]];
        $child=['attribute_id'=>20,'attribute_value_list'=>[['value_id'=>0,'original_value_name'=>'100']]];
        ShopeeAttributePresence::validate($this->tree(),[$parent,$child]);
        ShopeeAttributePresence::validate($this->tree(),[['attribute_id'=>10,'attribute_value_list'=>[['value_id'=>2]]]]);
        $this->expectException(\DomainException::class);
        ShopeeAttributePresence::validate($this->tree(),[$parent]);
    }

    public function test_inactive_or_duplicate_attributes_cannot_satisfy_the_form(): void
    {
        $parent=['attribute_id'=>10,'attribute_value_list'=>[['value_id'=>2]]];
        foreach ([[$parent,$parent],[$parent,['attribute_id'=>20,'attribute_value_list'=>[['value_id'=>0]]]],[]] as $selection) {
            try {ShopeeAttributePresence::validate($this->tree(),$selection);$this->fail('Invalid attribute selection accepted.');}
            catch (\DomainException $error) {$this->assertNotEmpty($error->getMessage());}
        }
    }
}
