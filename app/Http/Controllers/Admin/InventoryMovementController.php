<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryMovementController extends Controller
{
    public function physical(Request $request)
    {
        $filters=$request->validate(['variantId'=>'required|integer|min:1','page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|min:1|max:100']);
        abort_unless(DB::table('product_variants')->where('id',$filters['variantId'])->exists(),404);
        $page=DB::table('inventory_physical_movements as m')
            ->join('inventory_balances as b','b.id','=','m.inventory_balance_id')
            ->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
            ->where('b.product_variant_id',$filters['variantId'])
            ->select(['m.id','m.review_id','m.inventory_balance_id','m.actor_id','m.physical_before','m.physical_after',
                'm.physical_delta','m.balance_version','m.reason','m.created_at','b.product_variant_id','p.kind as pool_kind','p.warehouse_id'])
            ->orderByDesc('m.id')->paginate($filters['per_page']??25);
        return response()->json($page)->header('Cache-Control','no-store');
    }

    public function index(Request $request)
    {
        $filters=$request->validate(['variantId'=>'required|integer|min:1',
            'page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|min:1|max:100']);
        abort_unless(DB::table('product_variants')->where('id',$filters['variantId'])->exists(),404);
        $page=DB::table('inventory_movements')->where('product_variant_id',$filters['variantId'])
            ->select('id','product_variant_id','operation_key','available_delta','available_after',
                'stock_version','actor_id','reason','created_at')
            ->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
        return response()->json($page)->header('Cache-Control','no-store');
    }
}
