<?php
namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/** Diagnostic read model, never an authorization to enable accounting. */
class InventoryReadinessService
{
    public function inspect(): array
    {
        $configuration=DB::table('inventory_configuration')->where('id',1)->first();
        $mode=$configuration?->operating_mode;
        $poolKind=match($mode){'shared_pool'=>'shared','independent_warehouses'=>'warehouse',default=>null};
        $unverified=0;$balances=0;
        if($poolKind){
            DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
                ->where('p.kind',$poolKind)->select('b.*')->orderBy('b.id')->chunk(200,function($rows)use(&$unverified,&$balances){
                    foreach($rows as $row){$balances++;if(!app(PhysicalBalanceReader::class)->project($row)['verified'])$unverified++;}
                });
        }
        $unowned=(int)DB::table('stock_allocations')->where('status','reserved')->whereNull('inventory_balance_id')->count();
        $transit=(int)DB::table('inventory_transfers')->where('status','in_transit')->count();
        $disabledRoutes=(int)DB::table('inventory_fulfillment_routes')->where('is_enabled',false)->count();
        $invalidRoutes=(int)DB::table('inventory_fulfillment_routes as r')->join('inventory_warehouses as w','w.id','=','r.warehouse_id')
            ->where('r.is_enabled',true)->where('w.status','!=','active')->count();
        $blockers=[];
        if(!$poolKind)$blockers[]='Operating mode has not been selected.';
        if(!$balances)$blockers[]='No balances exist for the selected operating mode.';
        if($unverified)$blockers[]='Physical counts, safety stock or order obligations require reconciliation.';
        if($unowned)$blockers[]='Open reservations have no physical warehouse ownership.';
        if($transit)$blockers[]='In-transit transfers must be resolved before mode conversion.';
        if($invalidRoutes)$blockers[]='Enabled routes point to inactive warehouses.';
        return ['operating_mode'=>$mode,'accounting_enabled'=>(bool)($configuration?->accounting_enabled??false),
            'configuration_version'=>(int)($configuration?->version??0),'balances'=>$balances,'unverified_balances'=>$unverified,
            'unowned_reservations'=>$unowned,'in_transit_transfers'=>$transit,'disabled_routes'=>$disabledRoutes,
            'invalid_enabled_routes'=>$invalidRoutes,'blockers'=>$blockers,
            'activation_ready'=>false,'coverage_verified'=>false];
    }
}
