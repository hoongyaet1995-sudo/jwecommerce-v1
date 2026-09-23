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
        $logistics = MarketplaceOrderLogistics::fromPayload($payload, $order->platform);
        $thumbnail = MarketplaceOrderImage::fromPayload($payload, $order->platform);
        $items = $payload['item_list'] ?? [];
        abort_unless(is_array($items), 409, 'Order items are unavailable. Fetch the order again.');
        if ($order->platform==='lazada') {
            $raw=$payload['order_items']??null;
            abort_unless(is_array($raw)&&array_is_list($raw),409,'Order items are unavailable. Fetch the order again.');
            $items=[];
            foreach ($raw as $line) {
                abort_unless(is_array($line),409,'Order items are unavailable. Fetch the order again.');
                $items[]=['item_id'=>$line['product_id']??null,'model_id'=>$line['sku_id']??null,
                    'order_item_id'=>$line['order_item_id']??null,'item_name'=>$line['name']??null,
                    'model_name'=>$line['variation']??null,'model_sku'=>$line['sku']??null,
                    'image_url'=>MarketplaceOrderImage::fromItem($line,'lazada'),
                    'model_quantity_purchased'=>1,'model_discounted_price'=>$line['paid_price']??$line['item_price']??null];
            }
        }

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
                'external_order_item_id'=>isset($item['order_item_id'])?(string)$item['order_item_id']:null,
                'external_product_id'=>$productId, 'external_variant_id'=>$modelId,
                'title'=>$item['item_name'] ?? null, 'variant_title'=>$item['model_name'] ?? null,
                'image_url'=>$order->platform==='lazada' ? ($item['image_url'] ?? null) : MarketplaceOrderImage::fromItem($item,'shopee'),
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
            'shipping_carrier'=>$logistics['shipping_carrier'],
            'tracking_number'=>$logistics['tracking_number'],
            'thumbnail_url'=>$thumbnail,
            'packages'=>array_map(fn ($package) => [
                'package_number'=>$package['package_number'] ?? null,
                'logistics_status'=>$package['logistics_status'] ?? null,
                'shipping_carrier'=>$package['shipping_carrier'] ?? null,
            ], $payload['package_list'] ?? []),
            // Read support does not imply a verified write/label integration.
            'capabilities'=>['fulfillment'=>false, 'airway_bill'=>false],
        ];
        $result['fulfillment_attempts']=DB::table('marketplace_fulfillment_attempts')->where('order_id',$id)
            ->orderByDesc('id')->limit(50)->get(['id','operation','status','created_at','updated_at'])
            ->map(function ($attempt) {
                foreach (['created_at','updated_at'] as $field) {
                    if ($attempt->$field!==null) $attempt->$field=\Carbon\CarbonImmutable::parse($attempt->$field,config('app.timezone'))->toIso8601String();
                }
                return $attempt;
            })->all();
        $result['label_packages']=[];
        $result['rts_packages']=[];
        $result['pack_item_ids']=[];
        if ($order->platform==='lazada') {
            $packages=[];$rts=[];
            foreach ($payload['order_items'] as $item) {
                $package=$item['package_id']??null;
                if (!is_string($package)||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$package)) continue;
                $eligible=in_array($item['status']??null,['packed','ready_to_ship','ready_to_ship_pending','shipped','delivered'],true)
                    &&in_array($item['delivery_option_sof']??null,[0,'0'],true)&&in_array($item['is_fbl']??null,[0,'0'],true)
                    &&in_array($item['is_digital']??null,[0,'0'],true);
                $packages[$package]=($packages[$package]??true)&&$eligible;
                $rts[$package]=($rts[$package]??true)&&$eligible&&($item['status']??null)==='packed'&&($item['semi_managed']??null)===false;
            }
            $result['label_packages']=array_keys(array_filter($packages));
            $testToken=\App\Models\MarketplaceStoreToken::where('store_id',$order->store_id)->first();
            $testAccessReady=$testToken && ($testToken->credential_source!=='official_test'
                || ($testToken->test_lease_expires_at?->isFuture() && $testToken->access_expires_at?->isFuture()));
            $result['capabilities']['airway_bill']=count($result['label_packages'])>0 && $testAccessReady;
            $store=DB::table('marketplace_stores')->find($order->store_id);
            if (config('marketplace.fulfillment_operations_enabled')===true&&$store->status==='connected'&&$store->region==='MY'&&$store->environment==='live') {
                $blockedItems=[];
                $packing=DB::table('marketplace_fulfillment_attempts')->where('order_id',$id)->where('operation','pack')
                    ->whereIn('status',['queued','processing','uncertain','partial','succeeded'])->get(['item_ids']);
                foreach ($packing as $attempt) $blockedItems=array_merge($blockedItems,json_decode($attempt->item_ids,true));
                foreach ($payload['order_items'] as $item) {
                    $itemId=(string)($item['order_item_id']??'');
                    if (preg_match('/\A[1-9][0-9]{0,29}\z/D',$itemId)&&!in_array($itemId,$blockedItems,true)
                        &&in_array($item['status']??null,['pending','repacked'],true)&&($item['semi_managed']??null)===false
                        &&in_array($item['delivery_option_sof']??null,[0,'0'],true)&&in_array($item['is_fbl']??null,[0,'0'],true)
                        &&in_array($item['is_digital']??null,[0,'0'],true)) $result['pack_item_ids'][]=$itemId;
                }
                $blocked=[];
                $attempts=DB::table('marketplace_fulfillment_attempts as a')->join('marketplace_orders as o','o.id','=','a.order_id')
                    ->where('o.store_id',$order->store_id)->where('a.operation','rts')->whereIn('a.status',['queued','processing','uncertain','partial','succeeded'])->select('a.request')->get();
                foreach ($attempts as $attempt) $blocked=array_merge($blocked,json_decode($attempt->request,true)['packages']??[]);
                $result['rts_packages']=array_values(array_diff(array_keys(array_filter($rts)),$blocked));
                $result['capabilities']['fulfillment']=count($result['rts_packages'])>0;
            }
        }
        if ($includeRecipient) {
            $address = is_array($payload['recipient_address'] ?? null) ? $payload['recipient_address'] : [];
            if ($order->platform==='lazada') {
                $shipping=is_array($payload['address_shipping']??null)?$payload['address_shipping']:[];
                $text=static fn ($key)=>is_string($shipping[$key]??null)&&trim($shipping[$key])!==''?trim($shipping[$key]):null;
                $address=['name'=>implode(' ',array_filter([$text('first_name'),$text('last_name')]))?:null,
                    'phone'=>$text('phone'),'city'=>$text('city'),'region'=>$text('country'),'zipcode'=>$text('post_code'),
                    'full_address'=>implode(', ',array_filter(array_map($text,['address1','address2','address3','address4','address5'])))?:null];
            }
            $result['recipient'] = [];
            foreach (['name', 'phone', 'town', 'district', 'city', 'state', 'region', 'zipcode', 'full_address'] as $field) {
                $result['recipient'][$field] = is_string($address[$field] ?? null) ? $address[$field] : null;
            }
        }
        return $result;
    }
}
