<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplacePublishingDispatchService;
use App\Services\Marketplace\PublishingTasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplacePublishingController extends Controller
{
    public function stage(Request $request, int $store, PublishingTasks $tasks)
    {
        if (array_diff(array_keys($request->all()), ['product_id', 'request_key', 'payload', '_token'])) {
            throw ValidationException::withMessages(['publishing'=>'Unsupported marketplace publishing fields.']);
        }
        $data=$request->validate([
            'product_id'=>['required','integer','min:1'],
            'request_key'=>['required','uuid'],
            'payload'=>['required','array'],
        ]);
        if (!$request->user() || !DB::table('users')->where('id',$request->user()->id)->where('is_admin',true)->exists()) {
            abort(403);
        }
        return response()->json(['id'=>$tasks->create($store,(int)$data['product_id'],(int)$request->user()->id,$data['request_key'],$data['payload']),
            'status'=>'queued'])->header('Cache-Control','no-store, private');
    }

    public function dispatch(Request $request, int $task, MarketplacePublishingDispatchService $dispatcher)
    {
        if (array_diff(array_keys($request->all()), ['_token'])) {
            throw ValidationException::withMessages(['publishing'=>'Marketplace publishing dispatch accepts no request fields.']);
        }
        return response()->json($dispatcher->dispatch($task))->header('Cache-Control','no-store, private');
    }

    public function show(int $task)
    {
        $row=DB::table('marketplace_publishing_tasks')->select([
            'id','store_id','product_id','status','external_item_id','created_at','updated_at',
        ])->find($task);
        abort_unless($row,404);

        return response()->json([
            'id'=>(int)$row->id,
            'storeId'=>(int)$row->store_id,
            'productId'=>(int)$row->product_id,
            'status'=>$row->status,
            'externalItemId'=>$row->external_item_id,
            'createdAt'=>$row->created_at,
            'updatedAt'=>$row->updated_at,
        ])->header('Cache-Control','no-store, private');
    }
}
