<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class MarketplacePublishingController extends Controller
{
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
