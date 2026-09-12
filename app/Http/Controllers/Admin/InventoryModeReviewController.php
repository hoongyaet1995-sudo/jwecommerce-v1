<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryModeReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryModeReviewController extends Controller
{
    public function store(Request $request,InventoryModeReviewService $service)
    {
        abort_unless(config('marketplace.advanced_wms_enabled'),403,'Advanced WMS mode conversion is disabled.');
        if(array_diff(array_keys($request->all()),['target_mode','strategy','reason','allocations','_token']))
            throw ValidationException::withMessages(['mode'=>'Unsupported mode review fields.']);
        $data=$request->validate(['target_mode'=>'required|in:shared_pool,independent_warehouses',
            'strategy'=>'required|in:primary_all,explicit_distribution','reason'=>'required|string|max:255',
            'allocations'=>'sometimes|array','allocations.*'=>'array','allocations.*.*'=>'integer|min:0|max:2147483647']);
        return response()->json($service->review($data['target_mode'],$data['strategy'],$data['reason'],
            $data['allocations']??[],$request->user()))->header('Cache-Control','no-store');
    }
}
