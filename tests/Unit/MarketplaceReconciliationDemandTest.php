<?php
namespace Tests\Unit;

use App\Services\Marketplace\MarketplaceReconciliationDemand;
use PHPUnit\Framework\TestCase;

class MarketplaceReconciliationDemandTest extends TestCase
{
    public function test_demand_is_classified_without_assuming_unpaid_or_shipped_stock_is_free(): void
    {
        $payload=['fulfillment_flag'=>'fulfilled_by_local_seller','item_list'=>[['item_id'=>12,'model_id'=>0,'model_quantity_purchased'=>3]]];
        $this->assertSame(['error'=>null,'kind'=>'outstanding','lines'=>[['key'=>'12:0','quantity'=>3]]], MarketplaceReconciliationDemand::inspect('READY_TO_SHIP',$payload));
        $this->assertSame('conditional', MarketplaceReconciliationDemand::inspect('UNPAID',$payload)['kind']);
        $this->assertSame('shipped_review', MarketplaceReconciliationDemand::inspect('SHIPPED',$payload)['kind']);
        $this->assertSame('unsupported_order_status', MarketplaceReconciliationDemand::inspect('NEW_STATUS',$payload)['error']);
        $payload['item_list'][]=$payload['item_list'][0];
        $this->assertSame('duplicate_order_line', MarketplaceReconciliationDemand::inspect('READY_TO_SHIP',$payload)['error']);
        $payload['item_list']=[$payload['item_list'][0]+['returned_qty'=>1]];
        $this->assertSame('unsupported_order_composition', MarketplaceReconciliationDemand::inspect('READY_TO_SHIP',$payload)['error']);
        $this->assertSame([], MarketplaceReconciliationDemand::inspect('READY_TO_SHIP',$payload)['lines']);
    }
}
