<?php
namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/** Snapshot material for review comparison; a fingerprint alone is not a commit lock. */
class InventoryModeSnapshot
{
    public function capture(): array
    {
        $rows=fn(string $table,array $columns)=>DB::table($table)->orderBy('id')->get($columns)->map(fn($row)=>(array)$row)->all();
        $snapshot=[
            'configuration'=>(array)DB::table('inventory_configuration')->where('id',1)->first(),
            'variants'=>$rows('product_variants',['id','sku','enabled','stock','stock_version']),
            'warehouses'=>$rows('inventory_warehouses',['id','status','is_default_master']),
            'pools'=>$rows('inventory_pools',['id','kind','warehouse_id']),
            'balances'=>$rows('inventory_balances',['id','inventory_pool_id','product_variant_id','physical_stock',
                'reserved_stock','safety_stock','version','counted_at','obligations_reconciled_at']),
            'reservations'=>DB::table('stock_allocations')->where('status','reserved')->orderBy('id')
                ->get(['id','owner_type','owner_id','line_key','product_variant_id','inventory_balance_id','quantity'])
                ->map(fn($row)=>(array)$row)->all(),
            'open_transfers'=>DB::table('inventory_transfers')->whereIn('status',['draft','in_transit'])->orderBy('id')
                ->get(['id','source_balance_id','destination_balance_id','quantity','received_quantity','status','version'])
                ->map(fn($row)=>(array)$row)->all(),
            'routes'=>$rows('inventory_fulfillment_routes',['id','channel_key','warehouse_id','platform_location_id','is_enabled','version']),
            'open_website_items'=>DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')
                ->whereIn('o.status',['pending_payment','paid','processing'])->orderBy('i.id')
                ->get(['i.id','i.order_id','i.product_variant_id','i.quantity','o.status'])->map(fn($row)=>(array)$row)->all(),
        ];
        return ['snapshot'=>$snapshot,'hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR))];
    }
}
