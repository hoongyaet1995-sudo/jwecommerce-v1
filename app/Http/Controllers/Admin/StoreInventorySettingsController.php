<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\ModeMutation;
use App\Services\Inventory\StoreManagement\ReadinessReader;
use App\Services\Inventory\StoreManagement\TakeoverMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoreInventorySettingsController extends Controller
{
    public function readiness(Request $request, int $store, ReadinessReader $reader): JsonResponse
    { abort_unless($request->user()?->is_super_admin, 403); return response()->json($reader->takeover($store)); }
    public function takeover(Request $request, int $store, TakeoverMutation $mutation): JsonResponse
    { $data=$request->validate(['reason'=>['required','string','max:255']]); return response()->json($mutation->apply($store,$request->user(),$data['reason'])); }
    public function pause(Request $request, int $store, TakeoverMutation $mutation): JsonResponse
    { return response()->json($mutation->pause($store,$request->user())); }
    public function mode(Request $request, ModeMutation $mutation): JsonResponse
    { $data=$request->validate(['target'=>['required','string'],'configuration_version'=>['required','integer'],'distribution'=>['present','array']]);
      return response()->json($mutation->switch($data['target'],$data['distribution'],$data['configuration_version'],$request->user())); }
}
