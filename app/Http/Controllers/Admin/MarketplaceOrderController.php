<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceOrderController extends Controller
{
    public function pack(Request $request,int $order,\App\Services\Marketplace\MarketplacePackingAttempts $attempts,\App\Services\Marketplace\LazadaPackingDispatch $dispatch)
    {
        abort_unless(config('marketplace.fulfillment_operations_enabled')===true,503,'Marketplace fulfillment is disabled.');
        $data=$request->validate(['requestKey'=>'required|uuid','itemIds'=>'required|array|min:1|max:100',
            'itemIds.*'=>'required|string|distinct|regex:/\A[1-9][0-9]{0,29}\z/D','allocationType'=>'required|in:TFS,NTFS']);
        try {
            $id=$attempts->stage($order,(int)$request->user()->id,$data['requestKey'],$data['itemIds'],$data['allocationType']);
            $task=DB::table('marketplace_fulfillment_attempts')->find($id);
            if ($task->status!=='queued') return response()->json(['id'=>$id,'status'=>$task->status])->header('Cache-Control','no-store');
            $result=$dispatch->dispatch($id);
            $result['snapshot_refreshed']=$this->refreshFulfillmentSnapshot($order);
            return response()->json($result)->header('Cache-Control','no-store');
        } catch (\DomainException) {
            return response()->json(['message'=>'This item selection already has a packing attempt or is invalid. Refresh and review.'],409)->header('Cache-Control','no-store');
        } catch (\RuntimeException) {
            $status=isset($id)?DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status'):null;
            return response()->json(['id'=>$id??null,'status'=>$status,'message'=>'Packing was not confirmed. Reconcile before resubmitting.'],409)->header('Cache-Control','no-store');
        }
    }

    public function fulfillmentAttempt(int $order,int $attempt)
    {
        $task=DB::table('marketplace_fulfillment_attempts')->where('id',$attempt)->where('order_id',$order)
            ->first(['id','order_id','operation','status','created_at','updated_at']);
        abort_unless($task,404);
        return response()->json($task)->header('Cache-Control','no-store');
    }

    public function reconcileRts(int $order,int $attempt,\App\Services\Marketplace\LazadaRtsReconciliation $reconciliation)
    {
        $task=DB::table('marketplace_fulfillment_attempts')->where('id',$attempt)->where('order_id',$order)->whereIn('operation',['rts','pack'])->first();
        abort_unless($task,404);
        try {
            $result=($task->operation==='pack' ? app(\App\Services\Marketplace\LazadaPackingReconciliation::class) : $reconciliation)->reconcile($attempt);
            if ($result['confirmed']) $result['snapshot_refreshed']=$this->refreshFulfillmentSnapshot($order);
        }
        catch (\RuntimeException) { return response()->json(['message'=>'Fulfillment could not be reconciled. The task remains protected against resubmission.'],409)->header('Cache-Control','no-store'); }
        return response()->json($result)->header('Cache-Control','no-store');
    }

    public function readyToShip(Request $request,int $order,\App\Services\Marketplace\MarketplaceRtsAttempts $attempts,\App\Services\Marketplace\LazadaRtsDispatch $dispatch)
    {
        abort_unless(config('marketplace.fulfillment_operations_enabled')===true,503,'Marketplace fulfillment is disabled.');
        $data=$request->validate(['requestKey'=>'required|uuid','packages'=>'required|array|min:1|max:20',
            'packages.*'=>'required|string|distinct|max:100|regex:/\A[A-Za-z0-9_-]+\z/D']);
        try {
            $id=$attempts->stage($order,(int)$request->user()->id,$data['requestKey'],$data['packages']);
            $task=DB::table('marketplace_fulfillment_attempts')->find($id);
            if ($task->status!=='queued') return response()->json(['id'=>$id,'status'=>$task->status])->header('Cache-Control','no-store');
            $result=$dispatch->dispatch($id);
            $result['snapshot_refreshed']=$this->refreshFulfillmentSnapshot($order);
            return response()->json($result)->header('Cache-Control','no-store');
        } catch (\DomainException) {
            return response()->json(['message'=>'This package selection already has an attempt or is invalid. Refresh and review before resubmitting.'],409)->header('Cache-Control','no-store');
        } catch (\RuntimeException) {
            $status=isset($id)?DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status'):null;
            return response()->json(['id'=>$id??null,'status'=>$status,'message'=>'RTS was not confirmed. Refresh and reconcile the task before retrying.'],409)->header('Cache-Control','no-store');
        }
    }

    private function refreshFulfillmentSnapshot(int $order):bool
    {
        try { app(\App\Services\Marketplace\LazadaOrderService::class)->refreshOrder($order); return true; }
        catch (\Throwable) { return false; }
    }

    public function label(Request $request,int $order,\App\Services\Marketplace\LazadaOrderLabel $labels)
    {
        $data=$request->validate(['packageId'=>'required|string|max:100|regex:/\A[A-Za-z0-9_-]+\z/D']);
        try { $bytes=$labels->download($order,$data['packageId']); }
        catch (\RuntimeException) { return response()->json(['message'=>'The label could not be verified. Refresh the order and try again.'],409)->header('Cache-Control','no-store'); }
        return response($bytes)->withHeaders(['Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="lazada-'.$data['packageId'].'.pdf"',
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Referrer-Policy'=>'no-referrer']);
    }

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

    public function fetch(Request $request, int $store, \App\Services\Marketplace\ShopeeClient $client, \App\Services\Marketplace\LazadaClient $lazada)
    {
        $data = $request->validate(['from'=>'required|integer|min:0', 'to'=>'required|integer|gt:from']);
        abort_if($data['to'] - $data['from'] > 30 * 86400 || $data['to'] > now()->timestamp, 422, 'Choose a past interval of at most 30 days.');
        $id = DB::transaction(function () use ($store, $data, $client, $lazada) {
            $row = DB::table('marketplace_stores')->where('id', $store)->lockForUpdate()->first();
            abort_unless($row, 404);
            $ready=$row->platform==='shopee' ? $client->isConfigured() && $row->environment===config('marketplace.shopee.environment')
                : ($row->platform==='lazada' && $lazada->isAuthorizationReady() && $row->environment==='live' && $row->region==='MY');
            abort_unless($row->status === 'connected' && $ready, 409, 'Connect and configure this store before fetching orders.');
            $active = DB::table('marketplace_order_runs')->where('store_id', $store)->whereIn('status', ['queued', 'running'])->first();
            if ($active) return $active->id;
            $id = DB::table('marketplace_order_runs')->insertGetId(['store_id'=>$store, 'status'=>'queued',
                'time_from'=>$data['from'], 'time_to'=>min($data['to'], $data['from'] + 14 * 86400),
                'requested_time_from'=>$data['from'], 'requested_time_to'=>$data['to'],
                'created_at'=>now(), 'updated_at'=>now()]);
            $this->assertAtomicQueue();
            $row->platform==='shopee' ? \App\Jobs\PullShopeeOrders::dispatch($id)->beforeCommit() : \App\Jobs\PullLazadaOrders::dispatch($id)->beforeCommit();
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

    public function retry(int $run, \App\Services\Marketplace\ShopeeClient $client, \App\Services\Marketplace\LazadaClient $lazada)
    {
        $initial = DB::table('marketplace_order_runs')->find($run);
        abort_unless($initial, 404);
        DB::transaction(function () use ($run, $initial, $client, $lazada) {
            // The same store lock serializes fetch and retry submissions.
            $store = DB::table('marketplace_stores')->where('id', $initial->store_id)->lockForUpdate()->first();
            $ready=$store&&($store->platform==='shopee' ? $client->isConfigured() && $store->environment===config('marketplace.shopee.environment')
                : $store->platform==='lazada' && $lazada->isAuthorizationReady() && $store->environment==='live' && $store->region==='MY');
            abort_unless($store && $store->status === 'connected' && $ready, 409, 'Reconnect the store before retrying.');
            $row = DB::table('marketplace_order_runs')->where('id', $run)->lockForUpdate()->first();
            abort_unless($row && $row->status === 'failed', 409, 'Only failed runs can be retried.');
            abort_if(DB::table('marketplace_order_runs')->where('store_id', $store->id)->whereIn('status', ['queued', 'running'])->exists(),
                409, 'Another order run is already active for this store.');
            DB::table('marketplace_order_runs')->where('id', $run)->update([
                'status'=>'queued', 'error_code'=>null, 'finished_at'=>null, 'updated_at'=>now(),
            ]);
            $this->assertAtomicQueue();
            $store->platform==='shopee' ? \App\Jobs\PullShopeeOrders::dispatch($run, $row->pages_completed)->beforeCommit() : \App\Jobs\PullLazadaOrders::dispatch($run, $row->pages_completed)->beforeCommit();
        });

        return response()->json(['runId'=>$run], 202);
    }

    private function assertAtomicQueue(): void
    {
        $queue = config('queue.connections.marketplace');
        if (($queue['driver'] ?? null) !== 'database'
            || ($queue['connection'] ?? config('database.default')) !== DB::connection()->getName()) {
            throw new \RuntimeException('Order scheduling requires the application database queue.');
        }
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
                'o.currency', 'o.total_amount', 'o.fetched_at', 'o.data_classification', 's.platform', 's.shop_name');
        if (isset($filters['storeId'])) $query->where('o.store_id', $filters['storeId']);
        if (!empty($filters['platform'])) $query->where('s.platform', $filters['platform']);
        if (!empty($filters['status'])) $query->where('o.platform_status', $filters['status']);
        if (!empty($filters['q'])) $query->where('o.external_order_id', 'like', '%'.$filters['q'].'%');

        return response()->json($query->orderByDesc('o.platform_updated_at')->orderByDesc('o.id')
            ->paginate($filters['per_page'] ?? 50))->header('Cache-Control', 'no-store');
    }

    public function summary(Request $request)
    {
        $filters = $request->validate(['storeId'=>'nullable|integer',
            'platform'=>'nullable|in:shopee,lazada,tiktok,shopify','q'=>'nullable|string|max:100']);
        $query = DB::table('marketplace_orders as o')->join('marketplace_stores as s','s.id','=','o.store_id');
        if (isset($filters['storeId'])) $query->where('o.store_id',$filters['storeId']);
        if (!empty($filters['platform'])) $query->where('s.platform',$filters['platform']);
        if (!empty($filters['q'])) $query->where('o.external_order_id','like','%'.$filters['q'].'%');
        // Status tabs describe the whole filtered scope, not the selected tab/page.
        $counts = $query->select('o.platform_status')->selectRaw('COUNT(*) AS aggregate_count')
            ->groupBy('o.platform_status')->get();
        $statuses = $counts->mapWithKeys(fn ($row)=>[$row->platform_status=>(int)$row->aggregate_count]);
        return response()->json(['total'=>$statuses->sum(),'statuses'=>(object)$statuses->all()])
            ->header('Cache-Control','no-store');
    }
}
