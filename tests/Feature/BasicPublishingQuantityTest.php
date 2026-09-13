<?php
namespace Tests\Feature;

use App\Services\Marketplace\BasicPublishingQuantity;
use Tests\TestCase;

class BasicPublishingQuantityTest extends TestCase
{
    public function test_lazada_nested_inventory_cannot_bypass_zero_initial_quantity(): void
    {
        foreach ([['Quantity'=>'1'],['SellableQuantity'=>7],['quantity'=>4],['Quantity'=>false]] as $stock) {
            $payload=['Request'=>['Product'=>['Skus'=>['Sku'=>[['quantity'=>'0',
                'MultiWarehouseInventories'=>['MultiWarehouseInventory'=>[['WarehouseCode'=>'MY-1']+$stock]]]]]]]];
            try { BasicPublishingQuantity::validate('lazada',$payload); $this->fail('Nonzero or ambiguous warehouse stock accepted'); }
            catch (\DomainException $e) { $this->assertStringContainsString('zero',$e->getMessage()); }
        }
    }
    public function test_explicit_zero_quantities_are_allowed_for_both_platforms(): void
    {
        BasicPublishingQuantity::validate('shopee',['seller_stock'=>[['stock'=>0,'location_id'=>'TEST']]]);
        BasicPublishingQuantity::validate('shopee',['original_stock'=>0]);
        BasicPublishingQuantity::validate('lazada',['Request'=>['Product'=>['Skus'=>['Sku'=>[['quantity'=>'0']]]]]]);
        $this->assertTrue(true);
    }

    public function test_positive_missing_and_ambiguous_quantities_are_rejected(): void
    {
        $cases=[['shopee',[]],['shopee',['original_stock'=>1]],['shopee',['original_stock'=>false]],
            ['shopee',['seller_stock'=>[['stock'=>0],['stock'=>5]]]],
            ['shopee',['original_stock'=>1,'seller_stock'=>[['stock'=>0]]]],
            ['lazada',['Request'=>['Product'=>['Skus'=>['Sku'=>[['quantity'=>'2']]]]]]],
            ['lazada',['Request'=>['Product'=>['Skus'=>['Sku'=>[[]]]]]]]];
        foreach ($cases as [$platform,$payload]) {
            try {BasicPublishingQuantity::validate($platform,$payload);$this->fail('Invalid quantity accepted');}
            catch (\DomainException $error) {$this->assertStringContainsString('zero',$error->getMessage());}
        }
    }
}
