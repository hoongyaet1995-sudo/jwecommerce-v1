<?php
namespace App\Services\Inventory;

/** Informational manual-ledger availability; never an order takeover or push authorization. */
class BasicWmsBalanceReader
{
    public function project(object $balance,int $ownedReserved):array
    {
        $physical=$balance->physical_stock===null?null:(int)$balance->physical_stock;
        $safety=$balance->safety_stock===null?null:(int)$balance->safety_stock;
        $known=$physical!==null && $physical>=0 && $safety!==null && $safety>=0
            && $ownedReserved>=0 && $balance->counted_at!==null;
        $net=$known?$physical-$ownedReserved-$safety:null;
        return ['physical_stock'=>$physical,'safety_stock'=>$safety,'owned_reserved'=>$ownedReserved,
            'available'=>$net===null?null:max(0,$net),'shortfall'=>$net===null?null:max(0,-$net),
            'order_coverage_complete'=>false,'platform_write_authorized'=>false,
            'basis'=>'manual_ledger_excluding_unallocated_orders'];
    }
}
