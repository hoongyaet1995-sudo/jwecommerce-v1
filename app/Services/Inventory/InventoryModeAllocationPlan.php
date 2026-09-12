<?php
namespace App\Services\Inventory;

use Illuminate\Validation\ValidationException;

/** Pure quantity planning; the caller supplies a locked, verified physical snapshot. */
class InventoryModeAllocationPlan
{
    public function build(array $physicalTotals,array $activeWarehouseIds,string $strategy,?int $primaryWarehouseId,array $explicit=[]): array
    {
        if(!in_array($strategy,['primary_all','explicit_distribution'],true))$this->reject('Unsupported allocation strategy.');
        $warehouses=array_fill_keys(array_map('strval',$activeWarehouseIds),true);
        if(!$warehouses)$this->reject('An active physical warehouse is required.');
        if($strategy==='primary_all'&&($primaryWarehouseId===null||!isset($warehouses[(string)$primaryWarehouseId])))
            $this->reject('Select an active primary warehouse.');
        if($strategy==='explicit_distribution'&&(array_diff_key($explicit,$physicalTotals)||array_diff_key($physicalTotals,$explicit)))
            $this->reject('The allocation plan must contain exactly the reviewed SKUs.');
        $plan=[];
        foreach($physicalTotals as $sku=>$total){
            if(!is_int($total)||$total<0||$total>2147483647)$this->reject('Every SKU requires a verified physical quantity.');
            $distribution=$strategy==='primary_all'?[(string)$primaryWarehouseId=>$total]:$explicit[$sku];
            if(!is_array($distribution)||!$distribution)$this->reject('Every SKU requires a warehouse allocation.');
            $sum=0;
            foreach($distribution as $warehouse=>$quantity){
                if(!isset($warehouses[(string)$warehouse])||!is_int($quantity)||$quantity<0||$quantity>2147483647)
                    $this->reject('Invalid warehouse or allocation quantity.');
                $sum+=$quantity;
            }
            if($sum!==$total)$this->reject('Allocated quantities must conserve the reviewed physical stock for every SKU.');
            ksort($distribution,SORT_STRING);$plan[$sku]=$distribution;
        }
        ksort($plan,SORT_STRING);
        return $plan;
    }

    private function reject(string $message):never
    {
        throw ValidationException::withMessages(['allocation'=>$message]);
    }
}
