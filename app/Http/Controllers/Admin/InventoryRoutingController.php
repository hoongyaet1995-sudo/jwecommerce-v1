<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRoutingController extends Controller
{
    public function stores(Request $request)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        return response()->json(DB::table('marketplace_stores')->whereIn('platform',['shopee','lazada'])
            ->select(['id','platform','shop_name','external_shop_id','status'])->orderBy('id')->paginate(100))->header('Cache-Control','no-store');
    }
    public function index(Request $request)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        return response()->json(DB::table('inventory_fulfillment_routes')->orderBy('id')->paginate(100))->header('Cache-Control','no-store');
    }

    public function save(Request $request, InventoryRoutingService $service)
    {
        if(array_diff(array_keys($request->all()),['channel_key','warehouse_id','version','platform_location_id','platform_location_name','fulfillment_priority','is_enabled','_token'])) {
            throw ValidationException::withMessages(['route'=>'Unsupported route fields.']);
        }
        $data=$request->validate(['channel_key'=>'required|string|max:80','warehouse_id'=>'required|integer|min:1',
            'version'=>'present|nullable|integer|min:0','platform_location_id'=>'nullable|string|max:128','is_enabled'=>'required|boolean',
            'platform_location_name'=>'nullable|string|max:255','fulfillment_priority'=>'sometimes|integer|min:1|max:2147483647']);
        return response()->json($service->save($data['channel_key'],(int)$data['warehouse_id'],
            $data['version']===null?null:(int)$data['version'],$data['platform_location_id']??null,(bool)$data['is_enabled'],$request->user(),
            $data['platform_location_name']??null,(int)($data['fulfillment_priority']??1)))->header('Cache-Control','no-store');
    }
}
