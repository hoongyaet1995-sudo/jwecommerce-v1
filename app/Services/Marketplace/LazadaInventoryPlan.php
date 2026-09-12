<?php
namespace App\Services\Marketplace;

use Carbon\CarbonImmutable;

/** Pure item-level normalization; the caller owns locks, tombstones and stock mutations. */
final class LazadaInventoryPlan
{
    public static function build(array $order,int $cutoff):array
    {
        $created=$order['created_at']??null;
        if (!is_string($created)||!preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [+-]\d{4}\z/D',$created)) {
            throw new MarketplaceInventoryException('invalid_order_time');
        }
        try { $time=CarbonImmutable::createFromFormat('!Y-m-d H:i:s O',$created); }
        catch (\Throwable) { throw new MarketplaceInventoryException('invalid_order_time'); }
        if (!$time || $time->format('Y-m-d H:i:s O')!==$created) throw new MarketplaceInventoryException('invalid_order_time');
        if ($time->getTimestamp()<=$cutoff) throw new MarketplaceInventoryException('historical_order');
        $orderId=self::id($order['order_id']??null);
        $items=$order['order_items']??null;
        if (!is_array($items)||!array_is_list($items)||!$items) throw new MarketplaceInventoryException('invalid_order_lines');
        $seen=[]; $plan=[];
        foreach ($items as $item) {
            if (!is_array($item)) throw new MarketplaceInventoryException('invalid_order_lines');
            $id=self::id($item['order_item_id']??null);
            if (isset($seen[$id])||self::id($item['order_id']??null)!==$orderId) throw new MarketplaceInventoryException('invalid_order_lines');
            $seen[$id]=true;
            if (!in_array($item['is_fbl']??null,[0,'0'],true)||($item['semi_managed']??null)!==false) {
                throw new MarketplaceInventoryException('unsupported_fulfillment');
            }
            $warehouse=$item['warehouse_code']??null;
            if (!is_string($warehouse)||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$warehouse)) throw new MarketplaceInventoryException('invalid_warehouse_code');
            $target=match ($item['status']??null) {
                'pending','packed','repacked','ready_to_ship','ready_to_ship_pending'=>'reserved',
                'shipped','delivered'=>'consumed',
                'canceled'=>'released',
                'unpaid'=>'waiting',
                default=>throw new MarketplaceInventoryException('unsupported_order_status'),
            };
            $plan[]=['line_key'=>$id,'external_product_id'=>self::id($item['product_id']??null),
                'external_variant_id'=>self::id($item['sku_id']??null),'quantity'=>1,
                'warehouse_code'=>$warehouse,'target'=>$target];
        }
        return $plan;
    }

    private static function id(mixed $value):string
    {
        if ((!is_string($value)&&!is_int($value))||!preg_match('/\A[1-9][0-9]{0,29}\z/D',(string)$value)) {
            throw new MarketplaceInventoryException('invalid_order_lines');
        }
        return (string)$value;
    }
}
