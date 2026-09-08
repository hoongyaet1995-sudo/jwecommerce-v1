<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReconciliationController extends Controller
{
    public function store(Request $request, InventoryReconciliationService $service)
    {
        $allowed=['balance_id','balance_version','stock_version','safety_stock','operation_key','reason','_token'];
        if(array_diff(array_keys($request->all()),$allowed)){
            throw ValidationException::withMessages(['inventory'=>'Unsupported reconciliation fields. The server derives reservations and operator identity.']);
        }
        $data=$request->validate(['balance_id'=>'required|integer|min:1','balance_version'=>'required|integer|min:0',
            'stock_version'=>'required|integer|min:0','safety_stock'=>'required|integer|min:0|max:2147483647',
            'operation_key'=>'required|string|max:128','reason'=>'required|string|max:255']);
        $result=$service->reconcile((int)$data['balance_id'],(int)$data['balance_version'],(int)$data['stock_version'],
            (int)$data['safety_stock'],$data['operation_key'],$data['reason'],$request->user());
        $before=json_decode(DB::table('inventory_reconciliations')->where('operation_key',$data['operation_key'])->value('before'),true,512,JSON_THROW_ON_ERROR);
        return response()->json(['receipt_id'=>$data['operation_key'],'status'=>'reconciled','physical_stock'=>$result['physical_stock'],
            'reserved_stock'=>$result['reserved_stock'],'safety_stock'=>$result['safety_stock'],'atp'=>$result['atp'],
            'balance_version'=>$result['version'],'previous_reserved_stock'=>$before['reserved_stock'],
            'previous_safety_stock'=>$before['safety_stock'],'previous_atp'=>$before['atp'],
            'timestamp'=>now()->toIso8601String()])->header('Cache-Control','no-store');
    }
}
