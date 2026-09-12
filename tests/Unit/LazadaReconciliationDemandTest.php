<?php
namespace Tests\Unit;
use App\Services\Marketplace\MarketplaceReconciliationDemand;
use PHPUnit\Framework\TestCase;

class LazadaReconciliationDemandTest extends TestCase
{
    public function test_same_sku_items_keep_independent_demand_and_mapping_identity():void
    {
        $line=['order_id'=>'100','product_id'=>'200','sku_id'=>'300','is_fbl'=>0,'semi_managed'=>false,'warehouse_code'=>'WH1','status'=>'pending'];
        $result=MarketplaceReconciliationDemand::inspect('pending',['order_id'=>'100','created_at'=>'2026-09-08 17:04:00 +0800',
            'order_items'=>[array_merge($line,['order_item_id'=>'101']),array_merge($line,['order_item_id'=>'102'])]],'lazada');
        $this->assertNull($result['error']);
        $this->assertSame('outstanding',$result['kind']);
        $this->assertSame(['101','102'],array_column($result['lines'],'key'));
        $this->assertSame(['200:300','200:300'],array_column($result['lines'],'mapping_key'));
        $this->assertSame([1,1],array_column($result['lines'],'quantity'));
    }
}
