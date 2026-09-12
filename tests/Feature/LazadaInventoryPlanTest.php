<?php
namespace Tests\Feature;

use App\Services\Marketplace\LazadaInventoryPlan;
use App\Services\Marketplace\MarketplaceInventoryException;
use Tests\TestCase;

class LazadaInventoryPlanTest extends TestCase
{
    private function item(string $id,string $status):array
    {
        return ['order_id'=>'100','order_item_id'=>$id,'product_id'=>'200','sku_id'=>'300',
            'status'=>$status,'warehouse_code'=>'MY-WH-1','is_fbl'=>0,'semi_managed'=>false];
    }
    private function order(array $items):array
    {
        return ['order_id'=>'100','created_at'=>'2026-09-08 14:33:58 +0800','order_items'=>$items];
    }
    public function test_same_sku_items_keep_separate_cancel_and_ship_actions():void
    {
        $plan=LazadaInventoryPlan::build($this->order([$this->item('101','canceled'),$this->item('102','shipped')]),1);
        $this->assertSame(['101','102'],array_column($plan,'line_key'));
        $this->assertSame(['released','consumed'],array_column($plan,'target'));
        $this->assertSame([1,1],array_column($plan,'quantity'));
        $this->assertSame(['MY-WH-1','MY-WH-1'],array_column($plan,'warehouse_code'));
    }
    public function test_ready_to_ship_remains_reserved_until_handover():void
    {
        $plan=LazadaInventoryPlan::build($this->order([$this->item('101','ready_to_ship')]),1);
        $this->assertSame('reserved',$plan[0]['target']);
    }
    public function test_duplicate_or_foreign_items_are_rejected():void
    {
        foreach ([[$this->item('101','pending'),$this->item('101','pending')],
            [array_replace($this->item('101','pending'),['order_id'=>'999'])]] as $items) {
            try { LazadaInventoryPlan::build($this->order($items),1); $this->fail('Invalid lines accepted'); }
            catch (MarketplaceInventoryException $error) { $this->assertSame('invalid_order_lines',$error->getMessage()); }
        }
    }
    public function test_historical_order_is_excluded():void
    {
        $this->expectException(MarketplaceInventoryException::class);
        $this->expectExceptionMessage('historical_order');
        LazadaInventoryPlan::build($this->order([$this->item('101','pending')]),2000000000);
    }
    public function test_return_does_not_imply_physical_restock():void
    {
        $this->expectException(MarketplaceInventoryException::class);
        $this->expectExceptionMessage('unsupported_order_status');
        LazadaInventoryPlan::build($this->order([$this->item('101','returned')]),1);
    }
}
