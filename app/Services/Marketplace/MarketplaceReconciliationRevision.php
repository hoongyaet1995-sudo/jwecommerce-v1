<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;

/** Internal review fingerprint; not proof of order coverage or activation approval. */
class MarketplaceReconciliationRevision
{
    /** Caller still owns admin authorization, coverage checks and activation eligibility. */
    public function withCurrent(int $storeId, string $revision, \Closure $save): mixed
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D',$revision)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['revision'=>'Invalid reconciliation version.']);
        }
        return DB::transaction(function () use ($storeId,$revision,$save) {
            // Nested read keeps its store/SKU locks until this outer transaction commits.
            if (!hash_equals($this->read($storeId),$revision)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['revision'=>'Inventory review changed. Reload reconciliation before confirming.']);
            }
            return $save();
        },3);
    }

    public function read(int $storeId): string
    {
        return DB::transaction(function () use ($storeId) {
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()
                ->first(['id','platform','status','environment','region','external_shop_id']);
            abort_unless($store,404);
            $hash=hash_init('sha256');
            $append=fn ($value)=>hash_update($hash,json_encode($value,JSON_THROW_ON_ERROR)."\n");
            $append($store);
            // Same store lock as mapping and ingestion. Stable SKU locks serialize shared stock writers.
            $variants=DB::table('product_variants as v')->whereExists(fn ($q)=>$q->selectRaw('1')
                ->from('marketplace_variant_mappings as m')->whereColumn('m.product_variant_id','v.id')->where('m.store_id',$storeId))
                ->orWhereExists(fn ($q)=>$q->selectRaw('1')->from('stock_allocations as a')
                    ->join('marketplace_orders as o','o.id','=','a.owner_id')->where('a.owner_type','marketplace_order')
                    ->where('o.store_id',$storeId)->whereColumn('a.product_variant_id','v.id'))
                ->orderBy('v.id')->lockForUpdate()->get(['v.id','v.stock','v.stock_version','v.enabled']);
            foreach ($variants as $row) $append($row);
            foreach (DB::table('marketplace_variant_mappings as m')->join('marketplace_listing_variants as l','l.id','=','m.listing_variant_id')
                ->where('m.store_id',$storeId)->orderBy('m.id')->cursor(['m.id','m.listing_variant_id','m.product_variant_id','l.external_product_id','l.external_variant_id']) as $row) $append($row);
            foreach (DB::table('marketplace_orders')->where('store_id',$storeId)->orderBy('id')
                ->cursor(['id','platform_status','platform_updated_at']) as $row) $append($row);
            foreach (DB::table('stock_allocations as a')->join('marketplace_orders as o','o.id','=','a.owner_id')
                ->where('a.owner_type','marketplace_order')->where('o.store_id',$storeId)->orderBy('a.id')
                ->cursor(['a.id','a.owner_id','a.line_key','a.product_variant_id','a.quantity','a.status']) as $row) $append($row);
            foreach (DB::table('marketplace_order_inventory as i')->join('marketplace_orders as o','o.id','=','i.order_id')
                ->where('o.store_id',$storeId)->orderBy('i.id')
                ->cursor(['i.order_id','i.platform_updated_at','i.status','i.terminal_status','i.error_code']) as $row) $append($row);
            $append(DB::table('marketplace_inventory_policies')->where('store_id',$storeId)
                ->first(['enabled','orders_created_after','reconciled_at']));
            return hash_final($hash);
        },3);
    }
}
