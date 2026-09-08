<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransferController extends Controller
{
    public function index(Request $request)
    {
        $filters=$request->validate(['page'=>'sometimes|integer|min:1','status'=>'sometimes|in:draft,in_transit,completed,cancelled']);
        $query=DB::table('inventory_transfers as t')
            ->join('product_variants as v','v.id','=','t.product_variant_id')->join('products as product','product.id','=','v.product_id')
            ->join('inventory_balances as sb','sb.id','=','t.source_balance_id')->join('inventory_pools as sp','sp.id','=','sb.inventory_pool_id')
            ->join('inventory_warehouses as sw','sw.id','=','sp.warehouse_id')
            ->join('inventory_balances as db','db.id','=','t.destination_balance_id')->join('inventory_pools as dp','dp.id','=','db.inventory_pool_id')
            ->join('inventory_warehouses as dw','dw.id','=','dp.warehouse_id')
            ->select(['t.id','t.operation_key','t.product_variant_id','t.source_balance_id','t.destination_balance_id','t.quantity','t.received_quantity','t.status','t.version','t.actor_id','t.notes','t.dispatched_at','t.completed_at','t.created_at',
                'v.sku','v.title as variant_name','product.title as product_name','sw.id as source_warehouse_id','sw.name as source_warehouse_name','dw.id as destination_warehouse_id','dw.name as destination_warehouse_name']);
        if(isset($filters['status']))$query->where('t.status',$filters['status']);
        $page=$query->orderByDesc('t.id')->paginate(100);
        $result=$page->toArray();
        $result['operating_mode']=DB::table('inventory_configuration')->where('id',1)->value('operating_mode');
        return response()->json($result)->header('Cache-Control','no-store');
    }
    public function store(Request $request,InventoryTransferService $service)
    {
        $this->fields($request,['source_balance_id','destination_balance_id','quantity','operation_key','notes']);
        $data=$request->validate(['source_balance_id'=>'required|integer|min:1','destination_balance_id'=>'required|integer|min:1',
            'quantity'=>'required|integer|min:1|max:2147483647','operation_key'=>'required|string|max:128','notes'=>'required|string|max:1000']);
        return response()->json($service->create((int)$data['source_balance_id'],(int)$data['destination_balance_id'],(int)$data['quantity'],
            $data['operation_key'],$data['notes'],$request->user()))->header('Cache-Control','no-store');
    }
    public function transition(Request $request,int $transfer,InventoryTransferService $service)
    {
        $this->fields($request,['action','quantity','operation_key','version','notes']);
        $data=$request->validate(['action'=>'required|in:dispatch,receive,cancel','quantity'=>'required|integer|min:0|max:2147483647',
            'operation_key'=>'required|string|max:128','version'=>'required|integer|min:0','notes'=>'nullable|string|max:1000']);
        return response()->json($service->transition($transfer,$data['action'],(int)$data['quantity'],$data['operation_key'],(int)$data['version'],$request->user(),$data['notes']??''))->header('Cache-Control','no-store');
    }
    private function fields(Request $request,array $allowed):void
    {
        if(array_diff(array_keys($request->all()),[...$allowed,'_token']))throw ValidationException::withMessages(['transfer'=>'Unsupported transfer fields.']);
    }
}
