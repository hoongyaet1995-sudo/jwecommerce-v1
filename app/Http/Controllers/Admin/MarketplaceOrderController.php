<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceOrderController extends Controller
{
    public function preview(Request $request, int $order)
    {
        abort_unless(app()->environment('local'), 404);
        abort_unless(DB::table('marketplace_orders')->where('id', $order)->exists(), 404);
        $manifestPath = public_path('build-marketplace-orders-preview/manifest.json');
        abort_unless(is_file($manifestPath), 503, 'Build the local order preview first.');
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entry = $manifest['src/previews/marketplace30/order.tsx'] ?? null;
        abort_unless($entry, 503);
        return response()->view('admin.marketplace-order-preview', ['entry'=>$entry, 'bootstrap'=>[
            'user'=>['name'=>$request->user()->name, 'email'=>$request->user()->email, 'avatar'=>null],
            'csrfToken'=>csrf_token(), 'logoutUrl'=>route('logout'),
        ]])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function show(Request $request, int $order, \App\Services\Marketplace\MarketplaceOrderDetailService $details)
    {
        $request->validate(['includeRecipient'=>'sometimes|boolean']);
        return response()->json(['data'=>$details->read($order, $request->boolean('includeRecipient'))])->header('Cache-Control', 'no-store');
    }

    public function fetch(Request $request, int $store, \App\Services\Marketplace\ShopeeClient $client)
    {
        $data = $request->validate(['from'=>'required|integer|min:0', 'to'=>'required|integer|gt:from']);
        abort_if($data['to'] - $data['from'] > 14 * 86400 || $data['to'] > now()->timestamp, 422, 'Choose a past interval of at most 14 days.');
        abort_unless($client->isConfigured(), 409, 'Shopee is not configured.');
        $id = DB::transaction(function () use ($store, $data) {
            $row = DB::table('marketplace_stores')->where('id', $store)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'connected' && $row->platform === 'shopee'
                && $row->environment === config('marketplace.shopee.environment'), 409, 'Connect this store before fetching orders.');
            $active = DB::table('marketplace_order_runs')->where('store_id', $store)->whereIn('status', ['queued', 'running'])->first();
            if ($active) return $active->id;
            $id = DB::table('marketplace_order_runs')->insertGetId(['store_id'=>$store, 'status'=>'queued',
                'time_from'=>$data['from'], 'time_to'=>$data['to'], 'created_at'=>now(), 'updated_at'=>now()]);
            \App\Jobs\PullShopeeOrders::dispatch($id)->afterCommit();
            return $id;
        });

        return response()->json(['runId'=>$id], 202);
    }

    public function run(int $run)
    {
        $row = DB::table('marketplace_order_runs')->find($run);
        abort_unless($row, 404);
        return response()->json($row)->header('Cache-Control', 'no-store');
    }

    public function retry(int $run, \App\Services\Marketplace\ShopeeClient $client)
    {
        abort_unless($client->isConfigured(), 409, 'Shopee is not configured.');
        $initial = DB::table('marketplace_order_runs')->find($run);
        abort_unless($initial, 404);
        DB::transaction(function () use ($run, $initial) {
            // The same store lock serializes fetch and retry submissions.
            $store = DB::table('marketplace_stores')->where('id', $initial->store_id)->lockForUpdate()->first();
            abort_unless($store && $store->status === 'connected' && $store->platform === 'shopee'
                && $store->environment === config('marketplace.shopee.environment'), 409, 'Reconnect the store before retrying.');
            $row = DB::table('marketplace_order_runs')->where('id', $run)->lockForUpdate()->first();
            abort_unless($row && $row->status === 'failed', 409, 'Only failed runs can be retried.');
            abort_if(DB::table('marketplace_order_runs')->where('store_id', $store->id)->whereIn('status', ['queued', 'running'])->exists(),
                409, 'Another order run is already active for this store.');
            DB::table('marketplace_order_runs')->where('id', $run)->update([
                'status'=>'queued', 'error_code'=>null, 'finished_at'=>null, 'updated_at'=>now(),
            ]);
            \App\Jobs\PullShopeeOrders::dispatch($run, $row->pages_completed)->afterCommit();
        });

        return response()->json(['runId'=>$run], 202);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'storeId'=>'nullable|integer', 'platform'=>'nullable|in:shopee,lazada,tiktok,shopify',
            'status'=>'nullable|string|max:60', 'q'=>'nullable|string|max:100',
            'page'=>'nullable|integer|min:1', 'per_page'=>'nullable|integer|min:1|max:100',
        ]);
        $query = DB::table('marketplace_orders as o')->join('marketplace_stores as s', 's.id', '=', 'o.store_id')
            ->select('o.id', 'o.store_id', 'o.external_order_id', 'o.platform_status', 'o.platform_updated_at',
                'o.currency', 'o.total_amount', 'o.fetched_at', 's.platform', 's.shop_name');
        if (isset($filters['storeId'])) $query->where('o.store_id', $filters['storeId']);
        if (!empty($filters['platform'])) $query->where('s.platform', $filters['platform']);
        if (!empty($filters['status'])) $query->where('o.platform_status', $filters['status']);
        if (!empty($filters['q'])) $query->where('o.external_order_id', 'like', '%'.$filters['q'].'%');

        return response()->json($query->orderByDesc('o.platform_updated_at')->orderByDesc('o.id')
            ->paginate($filters['per_page'] ?? 50))->header('Cache-Control', 'no-store');
    }
}
