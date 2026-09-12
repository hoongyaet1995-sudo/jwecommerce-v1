<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\Marketplace\SandboxInventoryAttemptService;
use Illuminate\Http\Request;
class SandboxInventoryAttemptController extends Controller
{
 public function index(Request $request){
  $data=$request->validate(['variantId'=>'required|integer|min:1','page'=>'sometimes|integer|min:1']);
  $page=\Illuminate\Support\Facades\DB::table('sandbox_inventory_attempts as a')->join('inventory_balances as b','b.id','=','a.inventory_balance_id')
   ->where('b.product_variant_id',$data['variantId'])->select(['a.id','a.actor_id','a.store_id','a.reason','a.status','a.snapshot','a.before_quantity','a.observed_quantity','a.error_code','a.updated_at','b.product_variant_id'])
   ->orderByDesc('a.updated_at')->orderByDesc('a.id')->paginate(100);
  $page->getCollection()->transform(function($row){$row->snapshot=json_decode($row->snapshot,true,512,JSON_THROW_ON_ERROR);return $row;});
  return response()->json($page)->header('Cache-Control','no-store');
 }
 public function options(Request $request,\App\Services\Marketplace\SandboxInventoryWriteGuard $guard){
  $data=$request->validate(['variantId'=>'required|integer|min:1']);
  $enabled=config('marketplace.sandbox_stock_writes_enabled')===true&&config('marketplace.basic_wms_enabled')===true;
  if(!$enabled)return response()->json(['enabled'=>false,'options'=>[]])->header('Cache-Control','no-store');
  $db=\Illuminate\Support\Facades\DB::class;$options=[];
  $mode=$db::table('inventory_configuration')->where('id',1)->value('operating_mode');
  foreach(['lazada','shopee'] as $platform){
  $storeId=(int)config('marketplace.'.$platform.'.test_store_id');if($storeId<1)continue;
  $route=$db::table('inventory_fulfillment_routes')->where('channel_key','marketplace:'.$storeId)->first();
  $balances=$db::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')->where('b.product_variant_id',$data['variantId']);
  if($mode==='shared_pool')$balances->where('p.kind','shared');
  else $balances->where('p.kind','warehouse')->where('p.warehouse_id',$route?->warehouse_id??0);
  foreach($balances->pluck('b.id') as $balance){
   try{$options[]=$guard->review($storeId,(int)$balance,$request->user());}
   catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){continue;}
  }
  }
  return response()->json(['enabled'=>true,'options'=>$options])->header('Cache-Control','no-store');
 }
 public function store(Request $request,SandboxInventoryAttemptService $service){
  abort_unless(config('marketplace.sandbox_stock_writes_enabled')===true,403,'Sandbox inventory writes are disabled.');
  abort_if(array_diff(array_keys($request->all()),['store_id','balance_id','operation_key','reason','_token']),422,'Unsupported test stock fields.');
  $data=$request->validate(['store_id'=>'required|integer|min:1','balance_id'=>'required|integer|min:1','operation_key'=>'required|uuid','reason'=>'required|string|max:255']);
  return response()->json($service->stage((int)$data['store_id'],(int)$data['balance_id'],$data['operation_key'],$data['reason'],$request->user()))->header('Cache-Control','no-store');
 }
 public function dispatch(Request $request,string $attempt,SandboxInventoryAttemptService $service){
  abort_if(array_diff(array_keys($request->all()),['_token']),422,'Test stock request is fixed by its review.');
  return response()->json($service->dispatch($attempt,$request->user()))->header('Cache-Control','no-store');
 }
 public function show(Request $request,string $attempt,SandboxInventoryAttemptService $service){
  return response()->json($service->inspect($attempt,$request->user()))->header('Cache-Control','no-store');
 }
}
