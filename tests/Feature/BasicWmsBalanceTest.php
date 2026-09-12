<?php
namespace Tests\Feature;

use App\Services\Inventory\BasicWmsBalanceReader;
use Tests\TestCase;

class BasicWmsBalanceTest extends TestCase
{
    public function test_basic_available_does_not_claim_order_coverage():void
    {
        $result=app(BasicWmsBalanceReader::class)->project((object)[
            'physical_stock'=>20,'safety_stock'=>2,'counted_at'=>'2026-09-08 00:00:00',
        ],3);
        $this->assertSame(15,$result['available']);
        $this->assertFalse($result['order_coverage_complete']);
        $this->assertFalse($result['platform_write_authorized']);
        $this->assertSame(0,$result['shortfall']);
    }

    public function test_unknown_count_and_safety_stay_unknown_and_deficit_is_explicit():void
    {
        $reader=app(BasicWmsBalanceReader::class);
        foreach([[null,2,null],[20,null,'2026-09-08'],[20,2,null]] as [$physical,$safety,$counted]){
            $this->assertNull($reader->project((object)['physical_stock'=>$physical,'safety_stock'=>$safety,'counted_at'=>$counted],0)['available']);
        }
        $result=$reader->project((object)['physical_stock'=>2,'safety_stock'=>3,'counted_at'=>'2026-09-08'],1);
        $this->assertSame(0,$result['available']);
        $this->assertSame(2,$result['shortfall']);
    }
}
