<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PullShopeeCatalog;
use App\Services\Marketplace\ShopeeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MarketplaceCatalogController extends Controller
{
    private function filteredQuery(Request $request)
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
            ->select('l.id', 'l.store_id', 'l.external_product_id', 'l.external_variant_id', 'l.observed_data', 'l.fetched_at',
                's.platform', 's.shop_name', 'm.product_variant_id as jw_variant_id', 'v.sku as jw_sku');
        if (isset($data['storeId'])) $query->where('l.store_id', $data['storeId']);
        if (!empty($data['platform'])) $query->where('s.platform', $data['platform']);
        if (($data['status'] ?? '') === 'mapped') $query->whereNotNull('m.id');
        if (($data['status'] ?? '') === 'unmapped') $query->whereNull('m.id');
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
                ->select('l.*', 'm.product_variant_id as jw_variant_id', 'v.sku as jw_sku')
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

    public function fetch(int $store, ShopeeClient $client)
    {
        abort_unless($client->isConfigured(), 409, 'Shopee is not configured.');
        $id = DB::transaction(function () use ($store) {
            $row = DB::table('marketplace_stores')->where('id', $store)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->platform === 'shopee' && $row->status === 'connected'
                && $row->environment === config('marketplace.shopee.environment'), 409, 'Connect the store before fetching products.');
            $active = DB::table('marketplace_catalog_runs')->where('store_id', $store)->whereIn('status', ['queued', 'running'])->first();
            if ($active) {
                return $active->id;
            }
            $id = DB::table('marketplace_catalog_runs')->insertGetId([
                'store_id'=>$store, 'status'=>'queued', 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            PullShopeeCatalog::dispatch($id)->afterCommit();

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

    public function retry(int $run, ShopeeClient $client)
    {
        abort_unless($client->isConfigured(), 409, 'Shopee is not configured.');
        $initial = DB::table('marketplace_catalog_runs')->find($run);
        abort_unless($initial, 404);
        DB::transaction(function () use ($run, $initial) {
            $store = DB::table('marketplace_stores')->where('id', $initial->store_id)->lockForUpdate()->first();
            abort_unless($store && $store->status === 'connected' && $store->platform === 'shopee'
                && $store->environment === config('marketplace.shopee.environment'), 409, 'Reconnect the store before retrying.');
            $row = DB::table('marketplace_catalog_runs')->where('id', $run)->lockForUpdate()->first();
            abort_unless($row && $row->status === 'failed', 409, 'Only failed runs can be retried.');
            abort_if(DB::table('marketplace_catalog_runs')->where('store_id', $store->id)->whereIn('status', ['queued', 'running'])->exists(),
                409, 'Another catalog run is already active for this store.');
            DB::table('marketplace_catalog_runs')->where('id', $run)->update([
                'status'=>'queued', 'error_code'=>null, 'finished_at'=>null, 'updated_at'=>now(),
            ]);
            PullShopeeCatalog::dispatch($run, $row->pages_completed)->afterCommit();
        });

        return response()->json(['runId'=>$run], 202);
    }
}
