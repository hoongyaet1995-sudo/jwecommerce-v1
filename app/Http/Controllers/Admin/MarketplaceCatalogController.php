<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PullShopeeCatalog;
use App\Jobs\PullLazadaCatalog;
use App\Services\Marketplace\ShopeeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MarketplaceCatalogController extends Controller
{
    private function filteredQuery(Request $request, bool $includeStatus = true)
    {
        $data = $request->validate([
            'storeId'=>'nullable|integer', 'platform'=>'nullable|in:shopee,lazada,tiktok,shopify',
            'q'=>'nullable|string|max:150', 'status'=>'nullable|in:all,mapped,unmapped',
            'per_page'=>'nullable|integer|min:1|max:100', 'page'=>'nullable|integer|min:1',
        ]);
        $query = DB::table('marketplace_listing_variants as l')
            ->join('marketplace_stores as s', 's.id', '=', 'l.store_id')
            ->leftJoin('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'm.product_variant_id')
            ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
            ->select('l.id', 'l.store_id', 'l.external_product_id', 'l.external_variant_id', 'l.observed_data', 'l.fetched_at',
                's.platform', 's.shop_name', 'm.product_variant_id as jw_variant_id', 'v.sku as jw_sku',
                'v.product_id as jw_product_id', 'p.title as jw_product_title', 'v.title as jw_variant_title', 'v.price as jw_price_minor', 'v.stock as jw_legacy_stock', 'm.updated_at as mapped_at');
        if (isset($data['storeId'])) $query->where('l.store_id', $data['storeId']);
        if (!empty($data['platform'])) $query->where('s.platform', $data['platform']);
        if ($includeStatus && ($data['status'] ?? '') === 'mapped') $query->whereNotNull('m.id');
        if ($includeStatus && ($data['status'] ?? '') === 'unmapped') $query->whereNull('m.id');
        if (!empty($data['q'])) {
            $term = '%'.$data['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('l.external_product_id', 'like', $term)->orWhere('l.external_variant_id', 'like', $term)
                    ->orWhere('l.observed_data->sku', 'like', $term)->orWhere('l.observed_data->productTitle', 'like', $term)
                    ->orWhere('l.observed_data->title', 'like', $term)->orWhere('v.sku', 'like', $term);
            });
        }
        return $query;
    }

    public function summary(Request $request)
    {
        // Counts describe the search/store scope, never just the visible page or status tab.
        $query = $this->filteredQuery($request, false);
        $counts = (clone $query)->select([])->selectRaw('COUNT(*) as variants, COUNT(m.id) as mapped')->first();
        $products = DB::query()->fromSub((clone $query)->select('l.store_id', 'l.external_product_id')->distinct(), 'catalog_products')->count();

        return response()->json(['products'=>$products, 'variants'=>(int)$counts->variants,
            'mapped'=>(int)$counts->mapped, 'unmapped'=>(int)$counts->variants - (int)$counts->mapped])
            ->header('Cache-Control', 'no-store');
    }

    public function mappingContext(Request $request, int $listing)
    {
        $data = $request->validate(['variantIds'=>'required|array|min:1|max:100', 'variantIds.*'=>'required|integer|min:1|distinct']);
        $target = DB::table('marketplace_listing_variants')->find($listing);
        abort_unless($target, 404);
        // Inspect persisted same-store mappings, not the currently visible catalog page.
        $conflicts = DB::table('marketplace_variant_mappings as m')
            ->join('marketplace_listing_variants as l', 'l.id', '=', 'm.listing_variant_id')
            ->where('m.store_id', $target->store_id)->where('l.id', '!=', $listing)
            ->whereIn('m.product_variant_id', $data['variantIds'])
            ->get(['m.product_variant_id', 'l.id as listing_id', 'l.external_product_id', 'l.external_variant_id', 'l.observed_data'])
            ->keyBy('product_variant_id');
        $rows = DB::table('product_variants')->whereIn('id', $data['variantIds'])->orderBy('id')->get(['id'])->map(function ($variant) use ($conflicts) {
            $conflict = $conflicts->get($variant->id);
            $observed = $conflict ? (json_decode($conflict->observed_data, true) ?? []) : [];
            return ['jw_variant_id'=>$variant->id, 'same_store_conflict'=>$conflict ? [
                'listing_id'=>$conflict->listing_id, 'external_product_id'=>$conflict->external_product_id,
                'external_variant_id'=>$conflict->external_variant_id, 'product_title'=>$observed['productTitle'] ?? null,
                'variant_title'=>$observed['title'] ?? null, 'sku'=>$observed['sku'] ?? null,
            ] : null];
        });
        return response()->json(['data'=>$rows])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $page = $this->filteredQuery($request)->orderBy('l.id')->paginate($request->integer('per_page', 50));
        $page->through(function ($row) {
            $row->observed_data = json_decode($row->observed_data, true);
            return $row;
        });

        return response()->json($page)->header('Cache-Control', 'no-store');
    }

    public function products(Request $request)
    {
        $page = $this->filteredQuery($request)
            ->select('l.store_id', 'l.external_product_id', 's.platform', 's.shop_name')
            ->selectRaw('MIN(l.id) as first_listing_id')
            ->groupBy('l.store_id', 'l.external_product_id', 's.platform', 's.shop_name')
            ->orderBy('first_listing_id')->paginate($request->integer('per_page', 15));
        if ($page->isNotEmpty()) {
            // Fetch complete variant sets for only this page's products. Searching one SKU must not hide its siblings.
            $variants = DB::table('marketplace_listing_variants as l')
                ->leftJoin('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
                ->leftJoin('product_variants as v', 'v.id', '=', 'm.product_variant_id')
                ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
                ->select('l.*', 'm.product_variant_id as jw_variant_id', 'v.sku as jw_sku',
                    'v.product_id as jw_product_id', 'p.title as jw_product_title', 'v.title as jw_variant_title', 'v.price as jw_price_minor', 'v.stock as jw_legacy_stock', 'm.updated_at as mapped_at')
                ->where(function ($query) use ($page) {
                    foreach ($page->items() as $product) {
                        $query->orWhere(function ($pair) use ($product) {
                            $pair->where('l.store_id', $product->store_id)->where('l.external_product_id', $product->external_product_id);
                        });
                    }
                })->orderBy('l.id')->get()->map(function ($row) {
                    $row->observed_data = json_decode($row->observed_data, true);
                    return $row;
                })->groupBy(fn ($row) => json_encode([$row->store_id, $row->external_product_id]));
            $page->through(function ($product) use ($variants) {
                $product->variants = $variants->get(json_encode([$product->store_id, $product->external_product_id]), collect())->values();
                return $product;
            });
        }

        return response()->json($page)->header('Cache-Control', 'no-store');
    }

    public function fetch(int $store, ShopeeClient $client, \App\Services\Marketplace\LazadaClient $lazada)
    {
        $id = DB::transaction(function () use ($store, $client, $lazada) {
            $row = DB::table('marketplace_stores')->where('id', $store)->lockForUpdate()->first();
            abort_unless($row, 404);
            $ready=$row->platform==='shopee' ? app(\App\Services\Marketplace\ShopeeApplicationResolver::class)->isStoreReady((int)$row->id) : $row->platform==='lazada'&&$lazada->isConfigured()&&$row->environment==='live'&&$row->region==='MY';
            abort_unless($row->status === 'connected' && $ready, 409, 'Connect the store before fetching products.');
            $active = DB::table('marketplace_catalog_runs')->where('store_id', $store)->whereIn('status', ['queued', 'running'])->first();
            if ($active) {
                return $active->id;
            }
            $id = DB::table('marketplace_catalog_runs')->insertGetId([
                'store_id'=>$store, 'status'=>'queued', 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            $row->platform==='shopee' ? PullShopeeCatalog::dispatch($id)->afterCommit() : PullLazadaCatalog::dispatch($id)->afterCommit();

            return $id;
        });

        return response()->json(['runId'=>$id], 202);
    }

    public function show(int $run)
    {
        $row = DB::table('marketplace_catalog_runs')->find($run);
        abort_unless($row, 404);

        return response()->json($row)->header('Cache-Control', 'no-store');
    }

    public function latest(int $store)
    {
        abort_unless(DB::table('marketplace_stores')->where('id', $store)->exists(), 404);
        $run = DB::table('marketplace_catalog_runs')->where('store_id', $store)
            ->whereIn('status', ['queued', 'running', 'failed'])->orderByDesc('updated_at')->first();

        return response()->json($run)->header('Cache-Control', 'no-store');
    }

    public function retry(int $run, ShopeeClient $client, \App\Services\Marketplace\LazadaClient $lazada)
    {
        $initial = DB::table('marketplace_catalog_runs')->find($run);
        abort_unless($initial, 404);
        DB::transaction(function () use ($run, $initial, $client, $lazada) {
            $store = DB::table('marketplace_stores')->where('id', $initial->store_id)->lockForUpdate()->first();
            $shopeeReady = $store && $store->platform === 'shopee'
                && app(\App\Services\Marketplace\ShopeeApplicationResolver::class)->isStoreReady((int)$store->id);
            $lazadaReady = $store && $store->platform === 'lazada'
                && $lazada->isConfigured() && $store->environment === 'live' && $store->region === 'MY';
            abort_unless($store && $store->status === 'connected' && ($shopeeReady || $lazadaReady), 409,
                'Reconnect the store before retrying.');
            $row = DB::table('marketplace_catalog_runs')->where('id', $run)->lockForUpdate()->first();
            abort_unless($row && $row->status === 'failed', 409, 'Only failed runs can be retried.');
            abort_if(DB::table('marketplace_catalog_runs')->where('store_id', $store->id)->whereIn('status', ['queued', 'running'])->exists(),
                409, 'Another catalog run is already active for this store.');
            DB::table('marketplace_catalog_runs')->where('id', $run)->update([
                'status'=>'queued', 'error_code'=>null, 'finished_at'=>null, 'updated_at'=>now(),
            ]);
            $store->platform === 'shopee'
                ? PullShopeeCatalog::dispatch($run, $row->pages_completed)->afterCommit()
                : PullLazadaCatalog::dispatch($run, $row->pages_completed)->afterCommit();
        });

        return response()->json(['runId'=>$run], 202);
    }
}
