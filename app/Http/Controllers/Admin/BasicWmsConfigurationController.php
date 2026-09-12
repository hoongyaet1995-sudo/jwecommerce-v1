<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\BasicWmsConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BasicWmsConfigurationController extends Controller
{
    public function store(Request $request,BasicWmsConfigurationService $service)
    {
        abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS is disabled.');
        if(array_diff(array_keys($request->all()),['mode','version','reason','operation_key','_token']))
            throw ValidationException::withMessages(['mode'=>'Unsupported setup fields.']);
        $data=$request->validate(['mode'=>'required|in:shared_pool,independent_warehouses','version'=>'required|integer|min:0',
            'reason'=>'required|string|max:255','operation_key'=>'required|uuid']);
        return response()->json($service->configure($data['mode'],(int)$data['version'],$data['reason'],$data['operation_key'],$request->user()))
            ->header('Cache-Control','no-store');
    }
}
