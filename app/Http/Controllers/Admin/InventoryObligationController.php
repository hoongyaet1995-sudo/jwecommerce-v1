<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class InventoryObligationController extends Controller
{
    public function balance(Request $request, int $balance)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        return DB::transaction(function()use($balance){
            $identity=DB::table('inventory_balances')->find($balance);
            abort_unless($identity,404);
            $variant=DB::table('product_variants')->where('id',$identity->product_variant_id)->lockForUpdate()->first();
            $row=DB::table('inventory_balances')->where('id',$balance)->lockForUpdate()->first();
            $pool=DB::table('inventory_pools')->where('id',$row->inventory_pool_id)->first();
            $allocations=DB::table('stock_allocations')->where('inventory_balance_id',$balance)->where('status','reserved');
            $reserved=(int)(clone $allocations)->sum('quantity');
            $unowned=(int)DB::table('stock_allocations')->where('product_variant_id',$variant->id)->where('status','reserved')->whereNull('inventory_balance_id')->sum('quantity');
            $page=$allocations->select(['id','owner_type','owner_id','line_key','quantity'])->orderBy('id')->paginate(100);
            $page->setCollection(app(\App\Services\Inventory\InventoryReservationOrderReader::class)->enrich($page->getCollection()));
            return response()->json(['balance'=>app(\App\Services\Inventory\PhysicalBalanceReader::class)->project($row),
                'pool_kind'=>$pool->kind,'warehouse_id'=>$pool->warehouse_id===null?null:(int)$pool->warehouse_id,
                'stock_version'=>(int)$variant->stock_version,'legacy_available_stock'=>(int)$variant->stock,
                'derived_reserved_stock'=>$reserved,'unowned_reserved_stock'=>$unowned,
                'allocations'=>$page,
                'coverage_verified'=>false,'review_required'=>true])->header('Cache-Control','no-store');
        });
    }
    public function index(Request $request)
    {
        $data=$request->validate(['variantId'=>'required|integer|min:1']);
        $variant=DB::table('product_variants')->where('id',$data['variantId'])->first(['id','stock','stock_version']);
        abort_unless($variant,404);
        // All statuses remain visible: shipment/cancellation provenance requires review.
        $website=DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')
            ->where('i.product_variant_id',$variant->id)->groupBy('o.status')->orderBy('o.status')
            ->selectRaw('o.status, SUM(i.quantity) as quantity, COUNT(DISTINCT o.id) as order_count')->get();
        $marketplace=DB::table('stock_allocations')->where('product_variant_id',$variant->id)
            ->where('owner_type','marketplace_order')->groupBy('status')->orderBy('status')
            ->selectRaw('status, SUM(quantity) as quantity, COUNT(DISTINCT owner_id) as order_count')->get();
        $project=fn($rows)=>$rows->map(fn($row)=>['status'=>$row->status,'quantity'=>(int)$row->quantity,'order_count'=>(int)$row->order_count]);
        return response()->json(['variant_id'=>(int)$variant->id,'legacy_available_stock'=>(int)$variant->stock,
            'stock_version'=>(int)$variant->stock_version,'website_orders'=>$project($website),'marketplace_allocations'=>$project($marketplace),
            'physical_stock'=>null,'coverage_verified'=>false,'review_required'=>true])
            ->header('Cache-Control','no-store');
    }
}
