<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\Inventory\BasicWmsSafetyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
class BasicWmsSafetyController extends Controller {
 public function index(Request $request){
  $data=$request->validate(['variantId'=>'required|integer|min:1','page'=>'sometimes|integer|min:1']);
  abort_unless(DB::table('product_variants')->where('id',$data['variantId'])->exists(),404);
  $page=DB::table('basic_wms_safety_events as e')->join('inventory_balances as b','b.id','=','e.inventory_balance_id')
   ->join('inventory_pools as p','p.id','=','b.inventory_pool_id')->where('b.product_variant_id',$data['variantId'])
   ->select(['e.operation_key','e.receipt','e.created_at','b.product_variant_id','p.kind as pool_kind','p.warehouse_id'])
   ->orderByDesc('e.created_at')->orderByDesc('e.operation_key')->paginate(100);
  $page->getCollection()->transform(function($row){$row->receipt=json_decode($row->receipt,true,512,JSON_THROW_ON_ERROR);return $row;});
  return response()->json($page)->header('Cache-Control','no-store');
 }
 public function store(Request $request,BasicWmsSafetyService $service){
  abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS is disabled.');
  if(array_diff(array_keys($request->all()),['balance_id','balance_version','safety_stock','reason','operation_key','_token']))
   throw ValidationException::withMessages(['inventory'=>'Unsupported fields. Employee identity and reservations are server controlled.']);
  $data=$request->validate(['balance_id'=>'required|integer|min:1','balance_version'=>'required|integer|min:0',
   'safety_stock'=>'required|integer|min:0|max:2147483647','reason'=>'required|string|max:255','operation_key'=>'required|uuid']);
  return response()->json($service->set((int)$data['balance_id'],(int)$data['balance_version'],(int)$data['safety_stock'],
   $data['reason'],$data['operation_key'],$request->user()))->header('Cache-Control','no-store');
 }
}
