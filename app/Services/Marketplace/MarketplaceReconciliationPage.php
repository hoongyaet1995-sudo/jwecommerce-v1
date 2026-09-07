<?php

namespace App\Services\Marketplace;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JsonException;

class MarketplaceReconciliationPage
{
    public function annotate($orders): void
    {
        // Load only this page. Never attach encrypted payloads to response objects.
        $payloads = DB::table('marketplace_orders')->whereIn('id', $orders->getCollection()->pluck('id'))
            ->get(['id','encrypted_payload','platform_updated_at','platform_status'])->keyBy('id');
        foreach ($orders->getCollection() as $order) {
            $snapshot = $payloads->get($order->id);
            if (!$snapshot || (int)$snapshot->platform_updated_at !== (int)$order->platform_updated_at
                || $snapshot->platform_status !== $order->platform_status) {
                $order->demand = ['error'=>'snapshot_changed', 'kind'=>null, 'lines'=>[]];
                continue;
            }
            try {
                $payload = json_decode(Crypt::decryptString($snapshot->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
                $order->demand = is_array($payload)
                    ? MarketplaceReconciliationDemand::inspect($order->platform_status, $payload)
                    : ['error'=>'invalid_order_payload', 'kind'=>null, 'lines'=>[]];
            } catch (DecryptException|JsonException) {
                $order->demand = ['error'=>'invalid_order_payload', 'kind'=>null, 'lines'=>[]];
            }
        }
        // Resolve only identities found on this page; never reuse another store's mapping.
        $identities = [];
        foreach ($orders->getCollection() as $order) {
            foreach ($order->demand['lines'] as $line) {
                $identities[$order->store_id][$line['key']] = true;
            }
        }
        $mapped = [];
        foreach ($identities as $storeId=>$keys) {
            $itemIds = array_unique(array_map(fn ($key) => explode(':',$key)[0], array_keys($keys)));
            $rows = DB::table('marketplace_listing_variants as l')
                ->join('marketplace_variant_mappings as m','m.listing_variant_id','=','l.id')
                ->join('product_variants as v','v.id','=','m.product_variant_id')
                ->where('l.store_id',$storeId)->where('m.store_id',$storeId)
                ->whereIn('l.external_product_id',$itemIds)
                ->get(['l.external_product_id','l.external_variant_id','m.product_variant_id','v.stock','v.stock_version']);
            foreach ($rows as $row) $mapped[$storeId][$row->external_product_id.':'.$row->external_variant_id] = $row;
        }
        foreach ($orders->getCollection() as $order) {
            foreach ($order->demand['lines'] as &$line) {
                $mapping = $mapped[$order->store_id][$line['key']] ?? null;
                $line['product_variant_id'] = $mapping ? (int)$mapping->product_variant_id : null;
                $line['mapping_status'] = $line['product_variant_id'] === null ? 'unmapped' : 'mapped';
                $line['available_stock'] = $mapping ? (int)$mapping->stock : null;
                $line['stock_version'] = $mapping ? (int)$mapping->stock_version : null;
            }
            unset($line);
        }
    }
}
