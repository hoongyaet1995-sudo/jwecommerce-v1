<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\PhysicalBalanceReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhysicalBalanceController extends Controller
{
    public function transferOptions(Request $request, PhysicalBalanceReader $reader)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        $rows=DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
            ->join('inventory_warehouses as w','w.id','=','p.warehouse_id')->join('product_variants as v','v.id','=','b.product_variant_id')
            ->join('products as product','product.id','=','v.product_id')->where('p.kind','warehouse')->where('w.status','active')
            ->select(['b.*','w.id as warehouse_id','w.code as warehouse_code','w.name as warehouse_name','v.sku','v.title as variant_name','product.title as product_name'])
            ->orderBy('b.id')->paginate(100);
        $rows->setCollection($rows->getCollection()->map(fn($row)=>$reader->project($row)+[
            'warehouse_id'=>(int)$row->warehouse_id,'warehouse_code'=>$row->warehouse_code,'warehouse_name'=>$row->warehouse_name,
            'sku'=>$row->sku,'variant_name'=>$row->variant_name,'product_name'=>$row->product_name]));
        return response()->json($rows)->header('Cache-Control','no-store');
    }

    public function store(Request $request, \App\Services\Inventory\InventoryBalanceProvisioner $provisioner)
    {
        abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS balance setup is disabled.');
        if (array_diff(array_keys($request->all()),['variant_id','pool_kind','warehouse_id','_token'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['inventory'=>'Balance configuration cannot set stock quantities.']);
        }
        $data=$request->validate([
            'variant_id'=>'required|integer|min:1',
            'pool_kind'=>'required|in:shared,warehouse',
            'warehouse_id'=>'required_if:pool_kind,warehouse|prohibited_if:pool_kind,shared|nullable|integer|min:1',
        ]);
        return response()->json($provisioner->provision((int)$data['variant_id'],$data['pool_kind'],
            isset($data['warehouse_id'])?(int)$data['warehouse_id']:null))->header('Cache-Control','no-store');
    }

    public function index(Request $request, PhysicalBalanceReader $reader)
    {
        $data=$request->validate(['variantId'=>'required|integer|min:1','page'=>'sometimes|integer|min:1']);
        abort_unless(DB::table('product_variants')->where('id',$data['variantId'])->exists(),404);
        $rows=DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
            ->leftJoin('inventory_warehouses as w','w.id','=','p.warehouse_id')
            ->where('b.product_variant_id',$data['variantId'])->orderBy('b.id')
            ->select(['b.*','p.kind as pool_kind','p.warehouse_id','w.code as warehouse_code','w.name as warehouse_name'])
            ->paginate(100);
        $basicEnabled=(bool)config('marketplace.basic_wms_enabled');
        $reserved=$basicEnabled ? DB::table('stock_allocations')
            ->whereIn('inventory_balance_id',$rows->getCollection()->pluck('id'))
            ->where('status','reserved')->groupBy('inventory_balance_id')
            ->selectRaw('inventory_balance_id, SUM(quantity) as quantity')->pluck('quantity','inventory_balance_id') : collect();
        $rows->setCollection($rows->getCollection()->map(fn($row)=>$reader->project($row)+[
            'basic_wms'=>$basicEnabled ? app(\App\Services\Inventory\BasicWmsBalanceReader::class)
                ->project($row,(int)($reserved[$row->id]??0)) : null,
            'pool_kind'=>$row->pool_kind,
            'warehouse_id'=>$row->warehouse_id === null ? null : (int)$row->warehouse_id,
            'warehouse_code'=>$row->warehouse_code,'warehouse_name'=>$row->warehouse_name,
            'bin_location'=>$row->bin_location,
        ]));
        return response()->json($rows)->header('Cache-Control','no-store');
    }
}
