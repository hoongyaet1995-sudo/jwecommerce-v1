<?php
namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/** Diagnostic read model, never an authorization to enable accounting. */
class InventoryReadinessService
{
    public function inspect(): array
    {
        $configuration=DB::table('inventory_configuration')->where('id',1)->first();
        $setup=DB::table('basic_wms_configuration_events as e')->join('users as u','u.id','=','e.actor_id')
            ->orderByDesc('e.created_at')->first(['e.operation_key','e.receipt','u.name']);
        $setupAudit=null;
        if($setup){
            $receipt=json_decode($setup->receipt,true,512,JSON_THROW_ON_ERROR);
            $setupAudit=['id'=>$setup->operation_key,'timestamp'=>$receipt['created_at'],'fromMode'=>'unconfigured',
                'toMode'=>$receipt['operating_mode'],'staffName'=>$setup->name,'reason'=>$receipt['reason'],
                'affectedSkuCount'=>0,'summaryNote'=>'Initial configuration only. Inventory quantities and order accounting unchanged.'];
        }
        $mode=$configuration?->operating_mode;
        $poolKind=match($mode){'shared_pool'=>'shared','independent_warehouses'=>'warehouse',default=>null};
        $unverified=0;$balances=0;
        if($poolKind){
            DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
                ->where('p.kind',$poolKind)->select('b.*')->orderBy('b.id')->chunk(200,function($rows)use(&$unverified,&$balances){
                    foreach($rows as $row){$balances++;if(!app(PhysicalBalanceReader::class)->project($row)['verified'])$unverified++;}
                });
        }
        $missing=$poolKind ? (int)DB::table('product_variants as v')->where('v.enabled',true)
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('inventory_balances as b')
                ->join('inventory_pools as p','p.id','=','b.inventory_pool_id')->whereColumn('b.product_variant_id','v.id')->where('p.kind',$poolKind))->count() : 0;
        $unowned=(int)DB::table('stock_allocations')->where('status','reserved')->whereNull('inventory_balance_id')->count();
        $missingRoutes=0;$missingRoutedBalances=0;
        if($mode==='independent_warehouses') {
            $channels=DB::table('marketplace_inventory_policies')->where('enabled',true)->pluck('store_id')
                ->map(fn($id)=>'marketplace:'.$id)->prepend('website');
            $active=DB::table('inventory_fulfillment_routes as r')->join('inventory_warehouses as w','w.id','=','r.warehouse_id')
                ->where('r.is_enabled',true)->where('w.status','active')->whereIn('r.channel_key',$channels)->pluck('r.channel_key');
            $missingRoutes=$channels->diff($active)->count();
            $routes=DB::table('inventory_fulfillment_routes')->whereIn('channel_key',$active)->get(['channel_key','warehouse_id']);
            foreach($routes as $route) {
                $variants=DB::table('product_variants as v')->where('v.enabled',true);
                if($route->channel_key!=='website') {
                    $storeId=(int)substr($route->channel_key,strlen('marketplace:'));
                    $variants->whereExists(fn($q)=>$q->selectRaw('1')->from('marketplace_variant_mappings as m')
                        ->whereColumn('m.product_variant_id','v.id')->where('m.store_id',$storeId));
                }
                $missingRoutedBalances+=(int)$variants->whereNotExists(fn($q)=>$q->selectRaw('1')->from('inventory_balances as b')
                    ->join('inventory_pools as p','p.id','=','b.inventory_pool_id')->whereColumn('b.product_variant_id','v.id')
                    ->where('p.kind','warehouse')->where('p.warehouse_id',$route->warehouse_id))->count();
            }
        }
        $transit=(int)DB::table('inventory_transfers')->where('status','in_transit')->count();
        $disabledRoutes=(int)DB::table('inventory_fulfillment_routes')->where('is_enabled',false)->count();
        $invalidRoutes=(int)DB::table('inventory_fulfillment_routes as r')->join('inventory_warehouses as w','w.id','=','r.warehouse_id')
            ->where('r.is_enabled',true)->where('w.status','!=','active')->count();
        $blockers=[];
        if(!$poolKind)$blockers[]='Operating mode has not been selected.';
        if(!$balances)$blockers[]='No balances exist for the selected operating mode.';
        if($missing)$blockers[]='Some enabled variants have no balance for the selected operating mode.';
        if($missingRoutes)$blockers[]='Website or enabled marketplace accounting has no active fulfillment route.';
        if($missingRoutedBalances)$blockers[]='Some channel SKUs have no balance in their routed warehouse.';
        if($unverified)$blockers[]='Physical counts, safety stock or order obligations require reconciliation.';
        if($unowned)$blockers[]='Open reservations have no physical warehouse ownership.';
        if($transit)$blockers[]='In-transit transfers must be resolved before mode conversion.';
        if($invalidRoutes)$blockers[]='Enabled routes point to inactive warehouses.';
        return ['operating_mode'=>$mode,'accounting_enabled'=>(bool)($configuration?->accounting_enabled??false),
            'basic_wms_enabled'=>(bool)config('marketplace.basic_wms_enabled'),
            'basic_setup_audit'=>$setupAudit,
            'configuration_version'=>(int)($configuration?->version??0),'balances'=>$balances,'unverified_balances'=>$unverified,'missing_variant_balances'=>$missing,
            'unowned_reservations'=>$unowned,'in_transit_transfers'=>$transit,'disabled_routes'=>$disabledRoutes,
            'invalid_enabled_routes'=>$invalidRoutes,'missing_required_routes'=>$missingRoutes,'missing_routed_balances'=>$missingRoutedBalances,'blockers'=>$blockers,
            'activation_ready'=>false,'coverage_verified'=>false];
    }
}
