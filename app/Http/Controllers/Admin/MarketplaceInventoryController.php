<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketplaceInventoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['storeId'=>'nullable|integer|min:1', 'q'=>'nullable|string|max:100',
            'page'=>'nullable|integer|min:1', 'per_page'=>'nullable|integer|min:1|max:100']);
        $query = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id');
        if (isset($filters['storeId'])) {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('marketplace_variant_mappings as m')
                ->whereColumn('m.product_variant_id', 'v.id')->where('m.store_id', $filters['storeId']));
        }
        if (!empty($filters['q'])) {
            $query->where(fn ($sub) => $sub->where('v.sku', 'like', '%'.$filters['q'].'%')
                ->orWhere('v.title', 'like', '%'.$filters['q'].'%')->orWhere('p.title', 'like', '%'.$filters['q'].'%'));
        }
        $page = $query->select('v.id', 'v.product_id', 'v.sku', 'v.title as variant_title', 'p.title as product_title', 'v.stock')
            ->orderBy('v.id')->paginate($filters['per_page'] ?? 25);
        $ids = $page->getCollection()->pluck('id');
        // Aggregate only the displayed page; do not fetch the entire merchant catalog.
        $reserved = DB::table('stock_allocations')->whereIn('product_variant_id', $ids)
            ->where('owner_type', 'marketplace_order')->where('status', 'reserved')
            ->groupBy('product_variant_id')->selectRaw('product_variant_id, SUM(quantity) AS quantity')->pluck('quantity', 'product_variant_id');
        $links = DB::table('marketplace_variant_mappings as m')
            ->join('marketplace_listing_variants as l', 'l.id', '=', 'm.listing_variant_id')
            ->join('marketplace_stores as s', 's.id', '=', 'm.store_id')->whereIn('m.product_variant_id', $ids)
            ->select('m.product_variant_id', 'm.store_id', 's.shop_name', 's.platform', 's.status as connection_status',
                'l.external_product_id', 'l.external_variant_id')->orderBy('m.store_id')->orderBy('l.id')->get()->groupBy('product_variant_id');
        $page->setCollection($page->getCollection()->map(fn ($row) => [
            'id'=>(int) $row->id, 'product_id'=>(int) $row->product_id, 'sku'=>$row->sku,
            'product_title'=>$row->product_title, 'variant_title'=>$row->variant_title,
            'available_stock'=>(int) $row->stock, 'marketplace_reserved_stock'=>(int) ($reserved[$row->id] ?? 0),
            // Legacy availability is not a verified physical count; safety buffer has no owner yet.
            'physical_stock'=>null, 'safety_stock'=>null, 'reconciliation_required'=>true,
            'channel_links'=>($links[$row->id] ?? collect())->map(fn ($link) => [
                'store_id'=>(int) $link->store_id, 'shop_name'=>$link->shop_name, 'platform'=>$link->platform,
                'connection_status'=>$link->connection_status, 'external_product_id'=>$link->external_product_id,
                'external_variant_id'=>$link->external_variant_id, 'stock_push_enabled'=>false,
            ])->values(),
        ]));
        return response()->json($page)->header('Cache-Control', 'no-store');
    }

    public function exceptions(Request $request)
    {
        $filters = $request->validate([
            'storeId'=>'nullable|integer|min:1',
            'q'=>'nullable|string|max:100',
            'error'=>['nullable', Rule::in(['historical_order', 'unsupported_order_status', 'invalid_order_lines',
                'duplicate_order_line', 'unmapped_order_line', 'inventory_lifecycle_conflict', 'insufficient_stock',
                'unsupported_fulfillment', 'unsupported_order_composition'])],
            'page'=>'nullable|integer|min:1', 'per_page'=>'nullable|integer|min:1|max:100',
        ]);
        $query = DB::table('marketplace_order_inventory as i')
            ->join('marketplace_orders as o', 'o.id', '=', 'i.order_id')
            ->join('marketplace_stores as s', 's.id', '=', 'o.store_id')
            ->where('i.status', 'exception');
        if (isset($filters['storeId'])) $query->where('o.store_id', $filters['storeId']);
        if (isset($filters['error'])) $query->where('i.error_code', $filters['error']);
        if (!empty($filters['q'])) $query->where('o.external_order_id', 'like', '%'.$filters['q'].'%');
        // Explicit projection: encrypted order payloads and recipient data never enter this read model.
        $rows = $query->select('i.order_id', 'i.error_code', 'i.platform_updated_at', 'i.updated_at as checked_at',
            'o.external_order_id', 'o.platform_status', 'o.store_id', 's.platform', 's.shop_name', 's.status as connection_status')
            ->orderByDesc('i.updated_at')->orderByDesc('i.id')->paginate($filters['per_page'] ?? 25);
        return response()->json($rows)->header('Cache-Control', 'no-store');
    }
}
