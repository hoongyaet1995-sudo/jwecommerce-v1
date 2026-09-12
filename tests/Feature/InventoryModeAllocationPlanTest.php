<?php
namespace Tests\Feature;

use App\Services\Inventory\InventoryModeAllocationPlan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryModeAllocationPlanTest extends TestCase
{
    public function test_primary_and_explicit_plans_conserve_each_sku():void
    {
        $planner=new InventoryModeAllocationPlan;
        $this->assertSame(['A'=>[9=>20],'B'=>[9=>0]],$planner->build(['B'=>0,'A'=>20],[9,10],'primary_all',9));
        $this->assertSame(['A'=>[10=>5,9=>15]],$planner->build(['A'=>20],[9,10],'explicit_distribution',null,['A'=>[9=>15,10=>5]]));
    }

    public function test_unknown_stock_and_invalid_allocations_are_rejected():void
    {
        $planner=new InventoryModeAllocationPlan;
        $cases=[ [['A'=>null],[9],'primary_all',9,[]],
            [['A'=>20],[9],'explicit_distribution',null,['A'=>[9=>19]]],
            [['A'=>20],[9],'explicit_distribution',null,['A'=>[10=>20]]],
            [['A'=>20],[9],'explicit_distribution',null,['A'=>[9=>20],'B'=>[9=>0]]],
            [['A'=>20],[9],'explicit_distribution',null,['A'=>[9=>20.0]]],
            [['A'=>20],[9],'primary_all',10,[]] ];
        foreach($cases as $case){
            try{$planner->build(...$case);$this->fail('Invalid allocation was accepted.');}
            catch(ValidationException $error){$this->assertArrayHasKey('allocation',$error->errors());}
        }
    }
}
