<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;

/** Associate freshly read provider identities with the exact reviewed variants, never by SKU alone. */
final class PublishedCatalogMapping
{
    public function reconcile(int $storeId, array $rows): void
    {
        if (!$rows) return;
        DB::transaction(function () use ($storeId, $rows) {
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            if (!$store || !in_array($store->platform,['lazada','shopee'],true) || $store->status!=='connected') return;
            $tasks=DB::table('marketplace_publishing_tasks')->where('store_id',$storeId)->where('status','succeeded')
                ->whereIn('external_item_id',array_column($rows,'external_product_id'))->whereNotNull('source_snapshot')->get();
            foreach ($tasks as $task) {
                $source=json_decode($task->source_snapshot,true);
                $result=json_decode($task->confirmed_result ?? '',true);
                if (!is_array($source) || !is_array($result) || ($result['item_id']??null)!==$task->external_item_id
                    || ($source['product_id']??null)!==(int)$task->product_id
                    || !DB::table('users')->where('id',$task->actor_id)->where('is_admin',true)->exists()) continue;
                foreach ($source['variants']??[] as $variant) {
                    $externalSku=$result[$store->platform==='shopee'?'model_ids':'sku_ids'][$variant['sku']]??null;
                    if ($store->platform==='shopee' && !array_key_exists('model_ids',$result) && count($source['variants']??[])===1) $externalSku='0';
                    if (!is_string($externalSku)) continue;
                    foreach ($rows as $row) {
                        if ($row['external_product_id']!==$task->external_item_id || $row['external_variant_id']!==$externalSku) continue;
                        $observed=json_decode($row['observed_data'],true);
                        if (($observed['sku']??null)!==$variant['sku']) continue;
                        $listing=DB::table('marketplace_listing_variants')->where('store_id',$storeId)
                            ->where('external_product_id',$task->external_item_id)->where('external_variant_id',$externalSku)->first();
                        if (!$listing || DB::table('marketplace_mapping_events')->where('listing_variant_id',$listing->id)->exists()
                            || DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listing->id)->exists()) continue;
                        $current=DB::table('product_variants')->where('id',$variant['id'])->where('product_id',$task->product_id)
                            ->where('sku',$variant['sku'])->where('enabled',true)->lockForUpdate()->first();
                        if (!$current || DB::table('marketplace_variant_mappings')->where('store_id',$storeId)
                            ->where('product_variant_id',$current->id)->exists()) continue;
                        app(MarketplaceFoundationService::class)->map((int)$listing->id,(int)$current->id,
                            (int)$task->product_id,(int)$task->actor_id,['variant_id'=>null]);
                    }
                }
            }
        });
    }
}
