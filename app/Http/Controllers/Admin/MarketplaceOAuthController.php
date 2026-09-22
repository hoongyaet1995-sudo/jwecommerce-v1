<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\OAuthStateService;
use App\Services\Marketplace\ShopeeClient;
use App\Services\Marketplace\ShopeeAuthorizationService;
use App\Models\MarketplaceStoreToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Marketplace\MarketplaceStoreAccess;
use App\Models\MarketplaceOAuthBatch;
use App\Jobs\ProcessShopeeOAuthBatch;

class MarketplaceOAuthController extends Controller
{
    public function setProductPublishing(Request $request, int $store, MarketplaceStoreAccess $access)
    {
        abort_unless($request->user()?->is_admin && $request->user()?->is_super_admin, 403);
        $data=$request->validate([
            'enabled'=>['required','boolean'],
            'reason'=>['required','string','max:255'],
        ]);
        $access->setProductPublishing($store,(bool)$data['enabled'],(int)$request->user()->id,$data['reason']);

        return response()->json([
            'storeId'=>$store,
            'productPublish'=>(bool)DB::table('marketplace_store_capabilities')->where('store_id',$store)->value('product_publish'),
        ])->header('Cache-Control','no-store, private');
    }

    private function acceptanceEnabled(): bool
    {
        return config('marketplace.oauth_acceptance_enabled') === true
            && config('marketplace.shopee.environment') === 'sandbox';
    }

    public function acceptance(Request $request)
    {
        abort_unless($this->acceptanceEnabled(), 404);
        $path = public_path('build-marketplace-preview/manifest.json');
        abort_unless(is_file($path), 503, 'OAuth acceptance build is unavailable.');
        $entry = (json_decode(file_get_contents($path), true) ?? [])['src/previews/marketplace30/oauth.tsx'] ?? null;
        abort_unless($entry, 503, 'OAuth acceptance build is unavailable.');
        return response()->view('admin.marketplace-oauth-preview', ['entry'=>$entry,
            'pageTitle'=>'SANDBOX ACCEPTANCE — Connect Marketplace Store', 'bootstrap'=>[
                'user'=>['name'=>$request->user()->name, 'email'=>$request->user()->email, 'avatar'=>null],
                'csrfToken'=>csrf_token(), 'logoutUrl'=>route('logout'),
            ]])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function result(Request $request)
    {
        $receipt = $request->session()->get('marketplace.oauth_result');
        $result = null;
        if (is_array($receipt) && ($receipt['actorId'] ?? null) === $request->user()->id
            && ($receipt['expiresAt'] ?? 0) > now()->timestamp) {
            $result = ['status'=>$receipt['status'], 'store'=>null];
            if (!empty($receipt['batchId'])) {
                $batch=MarketplaceOAuthBatch::whereKey($receipt['batchId'])->where('actor_id',$request->user()->id)->first();
                if ($batch) {
                    $result['status']=$batch->status;
                    $result['batch']=$this->batchPayload($batch);
                }
                return response()->json(['result'=>$result])->header('Cache-Control','no-store');
            }
            if ($receipt['status'] === 'success') {
                $store = DB::table('marketplace_stores')->where('id', $receipt['storeId'])->where('status', 'connected')->first();
                if ($store) {
                    $result['store'] = ['id'=>(string) $store->id, 'platform'=>$store->platform,
                        'shopId'=>$store->external_shop_id, 'shopName'=>$store->shop_name];
                } else {
                    $result['status'] = 'failed';
                }
            }
        }
        if ($result===null) {
            $batch=MarketplaceOAuthBatch::where('actor_id',$request->user()->id)
                ->where('created_at','>',now()->subDay())->latest('id')->first();
            if ($batch) $result=['status'=>$batch->status,'store'=>null,'batch'=>$this->batchPayload($batch)];
        }
        return response()->json(['result'=>$result])->header('Cache-Control', 'no-store');
    }

    public function batch(Request $request, string $batch)
    {
        $record=MarketplaceOAuthBatch::where('public_id',$batch)->where('actor_id',$request->user()->id)->firstOrFail();
        return response()->json(['batch'=>$this->batchPayload($record)])->header('Cache-Control','no-store');
    }

    public function retryBatch(Request $request, string $batch)
    {
        $record=MarketplaceOAuthBatch::where('public_id',$batch)->where('actor_id',$request->user()->id)->firstOrFail();
        abort_if(!in_array($record->status,['partial','failed'],true),409,'This batch has no failed stores to retry.');
        if ($record->seed_refresh_expires_at && now()->greaterThanOrEqualTo($record->seed_refresh_expires_at)) {
            $record->update(['seed_access_token'=>null,'seed_refresh_token'=>null]);
        }
        abort_if(!$record->seed_refresh_token,409,'Reauthorize Main Account to continue.');
        $record->update(['status'=>'processing','completed_at'=>null]);
        ProcessShopeeOAuthBatch::dispatch($record->id,true);
        return response()->json(['batch'=>$this->batchPayload($record->fresh())],202)->header('Cache-Control','no-store');
    }

    private function batchPayload(MarketplaceOAuthBatch $batch): array
    {
        $batch->load('stores');
        return ['id'=>$batch->public_id,'status'=>$batch->status,'total'=>$batch->total_count,
            'connected'=>$batch->connected_count,'reauthorized'=>$batch->reauthorized_count,'failed'=>$batch->failed_count,
            'processing'=>$batch->stores->whereIn('status',['pending','processing'])->count(),
            'stores'=>$batch->stores->map(fn($row)=>['shopId'=>$row->external_shop_id,'storeId'=>$row->marketplace_store_id?(string)$row->marketplace_store_id:null,
                'status'=>$row->status==='succeeded'?$row->result_type:($row->status==='failed'?'requires_attention':'processing'),
                'message'=>$row->failure_message])->values()->all()];
    }

    public function connections(ShopeeClient $client, \App\Services\Marketplace\LazadaClient $lazada)
    {
        $successfulRuns = DB::table('marketplace_catalog_runs')->select('store_id','finished_at')->where('status','succeeded')
            ->unionAll(DB::table('marketplace_order_runs')->select('store_id','finished_at')->where('status','succeeded'));
        $lastSuccess = DB::query()->fromSub($successfulRuns,'successful_runs')->select('store_id')
            ->selectRaw('MAX(finished_at) as last_success')->groupBy('store_id')->pluck('last_success','store_id');
        $timestamp = fn($value) => $value ? \Illuminate\Support\Carbon::parse($value, config('app.timezone'))->utc()->toISOString() : null;
        // Select only public metadata; never load encrypted credentials into this response path.
        $stores = DB::table('marketplace_stores as s')->leftJoin('marketplace_store_tokens as t', 't.store_id', '=', 's.id')
            ->leftJoin('marketplace_store_capabilities as c', 'c.store_id', '=', 's.id')
            ->select('s.*', 't.access_expires_at', 't.refresh_expires_at', 't.application_id','t.credential_source','t.test_lease_expires_at',
                'c.catalog_read','c.product_publish','c.order_receive','c.inventory_update','c.fulfillment_write')
            ->selectRaw("CASE WHEN t.refresh_token IS NOT NULL AND t.refresh_token <> '' THEN 1 ELSE 0 END AS has_refresh_token")
            ->orderBy('s.id')->get()->map(function ($store) use ($lastSuccess, $timestamp) {
                $status = $store->status;
                $applicationMatches = $store->platform === 'shopee'
                    && $store->environment === config('marketplace.shopee.environment')
                    && $store->application_id !== null
                    && $store->application_id === (string) config('marketplace.shopee.partner_id');
                if ($store->platform === 'lazada') {
                    $expectedApp=$store->credential_source==='official_test'
                        ? (string)config('marketplace.lazada.test_app_key')
                        : (string)config('marketplace.lazada.app_key');
                    $applicationMatches = $store->environment === 'live' && $store->region === 'MY'
                        && config('marketplace.lazada.enabled') === true && $store->application_id !== null
                        && $store->application_id === $expectedApp;
                }
                // Match the token manager's refresh window without reading credentials or making HTTP calls.
                $accessUsable = $store->access_expires_at && now()->addMinutes(5)->lt($store->access_expires_at);
                if ($store->credential_source==='official_test') $accessUsable=\App\Services\Marketplace\LazadaOfficialTestAccess::environmentAllows($store, $store->application_id)
                    && $store->test_lease_expires_at && now()->lt($store->test_lease_expires_at);
                $canRefresh = (bool) $store->has_refresh_token
                    && $store->credential_source !== 'official_test'
                    && (!$store->refresh_expires_at || now()->lt($store->refresh_expires_at));
                if ($status === 'connected' && (!$applicationMatches || (!$accessUsable && !$canRefresh))) {
                    $status = 'reauthorization_required';
                }
                return ['id'=>(string) $store->id, 'platform'=>$store->platform, 'shopName'=>$store->shop_name,
                    'credentialSource'=>$store->credential_source,'testLeaseExpiresAt'=>$timestamp($store->test_lease_expires_at),
                    'shopId'=>$store->external_shop_id, 'region'=>$store->region, 'status'=>$status,
                    'accountMode'=>$store->account_mode,
                    'capabilities'=>[
                        'catalogRead'=>(bool)$store->catalog_read,'productPublish'=>(bool)$store->product_publish,
                        'orderReceive'=>(bool)$store->order_receive,'inventoryUpdate'=>(bool)$store->inventory_update,
                        'fulfillmentWrite'=>(bool)$store->fulfillment_write,
                    ],
                    'tokenExpiresAt'=>$timestamp($store->access_expires_at), 'lastSyncAt'=>$timestamp($lastSuccess->get($store->id)),
                    'autoSyncStock'=>false, 'autoPullOrders'=>false];
            });
        $unavailable = ['oauth'=>false, 'catalog'=>false, 'publish'=>false, 'orders'=>false, 'inventory'=>false, 'fulfillment'=>false];
        $readsEnabled = config('marketplace.read_operations_enabled') === true
            || config('marketplace.operations_enabled') === true;
        $publishingEnabled = config('marketplace.publish_operations_enabled') === true;
        $inventoryEnabled = config('marketplace.stock_sync_operations_enabled') === true;

        return response()->json(['stores'=>$stores, 'localCapabilities'=>[
            'importProducts'=>config('marketplace.import_operations_enabled') === true || config('marketplace.operations_enabled') === true,
        ], 'capabilities'=>[
            'shopee'=>array_replace($unavailable, ['oauth'=>$client->isAuthorizationReady(), 'catalog'=>$readsEnabled && $client->isConfigured(), 'orders'=>$readsEnabled && $client->isConfigured()]),
            'lazada'=>array_replace($unavailable, [
                'oauth'=>$lazada->isAuthorizationReady(),
                'catalog'=>$readsEnabled && $lazada->isConfigured(),
                'publish'=>$publishingEnabled && $lazada->isConfigured(),
                'orders'=>$readsEnabled && $lazada->isConfigured(),
                'inventory'=>$inventoryEnabled && $lazada->isConfigured(),
            ]),
            'tiktok'=>$unavailable, 'shopify'=>$unavailable,
        ]])->header('Cache-Control', 'no-store');
    }

    public function renewOfficialTestAccess(Request $request, int $store, \App\Services\Marketplace\LazadaTestConnectionService $service)
    {
        abort_unless($request->user()?->is_admin === true, 403);
        abort_unless(config('marketplace.lazada.online_test_access_enabled') === true
            && (string) config('marketplace.lazada.test_store_id') === (string) $store, 404);

        $target = DB::table('marketplace_stores')->where('id', $store)->first();
        abort_unless($target
            && $target->platform === 'lazada'
            && $target->environment === 'live'
            && $target->region === 'MY'
            && $target->external_shop_id === '300163725140'
            && $target->account_mode === 'test', 404);

        $data = $request->validate(['accessToken' => ['required', 'string', 'min:16', 'max:4096']]);

        try {
            $connectedStoreId = $service->connect($data['accessToken']);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Test access could not be verified. Generate a new token in Lazada API Explorer and try again.',
            ], 422)->header('Cache-Control', 'no-store');
        }

        abort_unless($connectedStoreId === $store, 409);
        $token = MarketplaceStoreToken::query()->where('store_id', $store)->firstOrFail();

        return response()->json(['store' => [
            'id' => (string) $store,
            'status' => 'connected',
            'credentialSource' => 'official_test',
            'testLeaseExpiresAt' => $token->test_lease_expires_at?->utc()->toISOString(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function disconnect(int $store)
    {
        DB::transaction(function () use ($store) {
            abort_unless(DB::table('marketplace_stores')->where('id', $store)->lockForUpdate()->first(), 404);
            DB::table('marketplace_stores')->where('id', $store)->update(['status'=>'disconnected', 'updated_at'=>now()]);
            DB::table('marketplace_store_tokens')->where('store_id', $store)->delete();
            // Includes callbacks already exchanging tokens outside the transaction.
            DB::table('marketplace_oauth_states')->where('reconnect_store_id', $store)
                ->update(['expires_at'=>now(), 'consumed_at'=>now()]);
        });

        return response()->json(['status'=>'disconnected', 'providerRevocationConfirmed'=>false]);
    }

    public function start(Request $request, OAuthStateService $states, ShopeeClient $client)
    {
        $request->session()->forget('marketplace.oauth_result');
        $data = $request->validate(['reconnectStoreId'=>'nullable|integer|exists:marketplace_stores,id']);
        $storeId = isset($data['reconnectStoreId']) ? (int) $data['reconnectStoreId'] : null;
        if ($storeId) {
            $store = DB::table('marketplace_stores')->find($storeId);
            abort_unless($store->platform === 'shopee' && $store->environment === config('marketplace.shopee.environment')
                && $store->external_shop_id !== null, 409, 'Store cannot be reauthorized with this provider configuration.');
        }
        try {
            // Check configuration before creating an attempt.
            if (!$client->isAuthorizationReady()) {
                throw new RuntimeException('Authorization callback does not target this deployment.');
            }
            $client->authorizationUrl(str_repeat('0', 64));
            $state = $states->issue($request->user()->id, 'shopee', config('app.url'), $storeId);
            return response()->json(['url'=>$client->authorizationUrl($state)])->header('Cache-Control', 'no-store');
        } catch (RuntimeException) {
            return response()->json(['message'=>'Shopee authorization is not available. Contact the system administrator.'], 409);
        }
    }

    public function callback(Request $request, OAuthStateService $states, ShopeeAuthorizationService $service)
    {
        $state = $request->query('state');
        $result = 'failed';
        $storeId = null;
        $batchId = null;
        if (is_string($state)) {
            if ($request->has('error') || !$request->filled('code')) {
                $attempt = $states->consume($state, $request->user()->id, 'shopee', config('app.url'));
                $result = $attempt ? 'cancelled' : 'failed';
            } else {
                $code = $request->query('code');
                $shopId = $request->query('shop_id');
                $mainAccountId=$request->query('main_account_id');
                $shopIdentity=is_string($shopId) && $shopId!=='';
                $mainIdentity=is_string($mainAccountId) && $mainAccountId!=='';
                if (is_string($code) && ($shopIdentity xor $mainIdentity)) {
                    try {
                        if ($shopIdentity) {
                            $storeId = $service->complete($state, $request->user()->id, $code, $shopId);
                            $result = 'success';
                        } else {
                            $batch=$service->completeMainAccount($state,$request->user()->id,$code,$mainAccountId);
                            $batchId=$batch->id;
                            $result=$batch->fresh()->status;
                        }
                    } catch (\Throwable) {
                        // Do not expose provider payloads, credentials or callback query strings.
                        $result = 'failed';
                    }
                }
            }
        }

        // Server-session receipt, not the redirect query, is authoritative for result presentation.
        // Keep it briefly across reloads/React effect retries; bind it to the initiating admin.
        $request->session()->put('marketplace.oauth_result', ['status'=>$result, 'storeId'=>$storeId,'batchId'=>$batchId,
            'actorId'=>$request->user()->id, 'expiresAt'=>now()->addMinutes(10)->timestamp]);
        return redirect('/admin/marketplace/auth?connection='.$result)
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
