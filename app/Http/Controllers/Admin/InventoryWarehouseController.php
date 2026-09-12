<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryWarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryWarehouseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        $rows=DB::table('inventory_warehouses as w')->leftJoin('inventory_configuration as c',function($join){
            $join->on('c.default_warehouse_id','=','w.id')->where('c.id',1);
        })->select(['w.*','c.default_warehouse_id'])->orderBy('w.id')->paginate(100);
        $rows->setCollection($rows->getCollection()->map(function($row){
            $data=(array)$row;
            $data['is_default_master']=$row->default_warehouse_id!==null;
            unset($data['default_warehouse_id']);
            return $this->project($data);
        }));
        return response()->json($rows)->header('Cache-Control','no-store');
    }

    public function store(Request $request, InventoryWarehouseService $service)
    {
        return $this->save($request,$service,null);
    }

    public function update(Request $request, int $warehouse, InventoryWarehouseService $service)
    {
        return $this->save($request,$service,$warehouse);
    }

    private function save(Request $request, InventoryWarehouseService $service, ?int $id)
    {
        abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS warehouse management is disabled.');
        $allowed=['code','name','status','is_default_master','address','state','contact_person','contact_phone','notes','supported_carriers','_token'];
        if ($id!==null) $allowed[]='version';
        if (array_diff(array_keys($request->all()),$allowed)) {
            throw ValidationException::withMessages(['warehouse'=>'Unsupported warehouse fields.']);
        }
        $version=$id===null?null:(int)$request->validate(['version'=>'required|integer|min:0'])['version'];
        $result=$service->save($id,$version,$request->except(['_token','version']),$request->user());
        return response()->json($this->project($result),$id===null?201:200)->header('Cache-Control','no-store');
    }

    private function project(array $data): array
    {
        $data['id']=(int)$data['id'];
        $data['version']=(int)$data['version'];
        $data['is_active']=(bool)$data['is_active'];
        $data['supported_carriers']=$data['supported_carriers']===null?[]:json_decode($data['supported_carriers'],true,512,JSON_THROW_ON_ERROR);
        return $data;
    }
}
