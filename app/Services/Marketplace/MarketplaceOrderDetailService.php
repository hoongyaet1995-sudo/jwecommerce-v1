<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MarketplaceOrderDetailService
{
    public function read(int $id, bool $includeRecipient = false): array
    {
        $order = DB::table('marketplace_orders as o')
            ->join('marketplace_stores as s', 's.id', '=', 'o.store_id')
            ->where('o.id', $id)->select('o.*', 's.platform', 's.shop_name')->first();
        abort_unless($order, 404);
        $payload = json_decode(Crypt::decryptString($order->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
        $items = $payload['item_list'] ?? [];
        abort_unless(is_array($items), 409, 'Order items are unavailable. Fetch the order again.');

        // Current mapping is advisory; never rewrite the historical snapshot or reserve stock here.
        $mappings = DB::table('marketplace_listing_variants as l')
            ->join('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
            ->join('product_variants as v', 'v.id', '=', 'm.product_variant_id')
            ->where('l.store_id', $order->store_id)
            ->whereIn('l.external_product_id', array_column($items, 'item_id'))
            ->select('l.external_product_id', 'l.external_variant_id', 'v.id', 'v.sku')
            ->get()->keyBy(fn ($row) => $row->external_product_id.':'.$row->external_variant_id);
        $lines = [];
        foreach ($items as $item) {
            $productId = isset($item['item_id']) ? (string) $item['item_id'] : null;
            $modelId = isset($item['model_id']) ? (string) $item['model_id'] : null;
            $mapping = $productId !== null && $modelId !== null ? $mappings->get($productId.':'.$modelId) : null;
            $lines[] = [
                'external_product_id'=>$productId, 'external_variant_id'=>$modelId,
                'title'=>$item['item_name'] ?? null, 'variant_title'=>$item['model_name'] ?? null,
                'external_sku'=>$item['model_sku'] ?? $item['item_sku'] ?? null,
                'quantity'=>$item['model_quantity_purchased'] ?? null,
                'unit_price'=>$item['model_discounted_price'] ?? $item['model_original_price'] ?? null,
                'mapping_status'=>$mapping ? 'mapped' : 'unmapped',
                'jw_variant_id'=>$mapping?->id, 'jw_sku'=>$mapping?->sku,
            ];
        }
        $result = [
            'id'=>$order->id, 'store_id'=>$order->store_id, 'external_order_id'=>$order->external_order_id,
            'platform'=>$order->platform, 'shop_name'=>$order->shop_name, 'platform_status'=>$order->platform_status,
            'currency'=>$order->currency, 'total_amount'=>$order->total_amount,
            'platform_updated_at'=>$order->platform_updated_at, 'fetched_at'=>$order->fetched_at,
            'lines'=>$lines,
            'shipping_carrier'=>$payload['shipping_carrier'] ?? null,
            'packages'=>array_map(fn ($package) => [
                'package_number'=>$package['package_number'] ?? null,
                'logistics_status'=>$package['logistics_status'] ?? null,
                'shipping_carrier'=>$package['shipping_carrier'] ?? null,
            ], $payload['package_list'] ?? []),
            // Read support does not imply a verified write/label integration.
            'capabilities'=>['fulfillment'=>false, 'airway_bill'=>false],
        ];
        if ($includeRecipient) {
            $address = is_array($payload['recipient_address'] ?? null) ? $payload['recipient_address'] : [];
            $result['recipient'] = [];
            foreach (['name', 'phone', 'town', 'district', 'city', 'state', 'region', 'zipcode', 'full_address'] as $field) {
                $result['recipient'][$field] = is_string($address[$field] ?? null) ? $address[$field] : null;
            }
        }
        return $result;
    }
}
