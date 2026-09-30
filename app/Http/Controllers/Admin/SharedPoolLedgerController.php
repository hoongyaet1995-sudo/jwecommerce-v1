<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class SharedPoolLedgerController extends Controller
{
    public function show(int $variant): JsonResponse
    {
        abort_unless(DB::table('inventory_configuration')->where('id', 1)->value('operating_mode') === 'shared_pool', 404);
        $sku = DB::table('product_variants')->where('id', $variant)->first(['id', 'sku']);
        abort_unless($sku, 404);

        $balance = DB::table('inventory_balances as b')
            ->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
            ->where('p.kind', 'shared')->where('b.product_variant_id', $variant)
            ->first(['b.id', 'b.current_stock']);
        $movements = $balance ? DB::table('inventory_current_stock_movements')
            ->where('inventory_balance_id', $balance->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(50)
            ->get(['id', 'delta', 'current_after', 'reason', 'created_at']) : collect();

        return response()->json([
            'variant_id' => $variant,
            'sku' => $sku->sku,
            'current_stock' => $balance?->current_stock === null ? null : (int) $balance->current_stock,
            'movements' => $movements,
        ])->header('Cache-Control', 'no-store');
    }
}
