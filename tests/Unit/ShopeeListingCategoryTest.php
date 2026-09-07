<?php

namespace Tests\Unit;

use App\Services\Marketplace\ShopeeListingCategory;
use PHPUnit\Framework\TestCase;

class ShopeeListingCategoryTest extends TestCase
{
    public function test_only_an_unambiguous_current_leaf_category_can_be_selected(): void
    {
        $rows=[['category_id'=>100,'has_children'=>true],['category_id'=>101,'has_children'=>false]];
        $this->assertSame(101,ShopeeListingCategory::requireLeaf($rows,101));
        foreach ([100,999] as $id) {
            try {ShopeeListingCategory::requireLeaf($rows,$id);$this->fail('Invalid selection must be rejected.');}
            catch (\DomainException $error) {$this->assertStringContainsString('category',$error->getMessage());}
        }
    }

    public function test_missing_leaf_flag_and_duplicate_category_are_not_accepted(): void
    {
        foreach ([[['category_id'=>101]], [['category_id'=>101,'has_children'=>false],['category_id'=>101,'has_children'=>false]]] as $rows) {
            try {ShopeeListingCategory::requireLeaf($rows,101);$this->fail('Ambiguous metadata must be rejected.');}
            catch (\DomainException $error) {$this->assertNotEmpty($error->getMessage());}
        }
    }
}
