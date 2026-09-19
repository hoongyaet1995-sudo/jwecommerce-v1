<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\ManagedInventoryActivation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagedInventoryActivationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->is_admin,403);
        return response()->json(['data'=>DB::table('inventory_order_activations')->orderBy('id')->paginate(100)])->header('Cache-Control','no-store');
    }

    public function store(Request $request,ManagedInventoryActivation $service)
    {
        abort_unless($request->user()?->is_admin,403);
        if (array_diff(array_keys($request->all()),['channel_key','balance_id','reason','_token'])) {
            throw ValidationException::withMessages(['activation'=>'Only channel, balance and reason may be supplied.']);
        }
        $data=$request->validate(['channel_key'=>['required','string','max:64'],'balance_id'=>['required','integer','min:1'],'reason'=>['required','string','max:2000']]);
        return response()->json(['data'=>$service->activate($data['channel_key'],(int)$data['balance_id'],$request->user(),$data['reason'])])->header('Cache-Control','no-store');
    }
}
