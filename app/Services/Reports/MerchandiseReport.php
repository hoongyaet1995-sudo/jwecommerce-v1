<?php
namespace App\Services\Reports;

use App\Models\Order;
use App\Services\Inventory\CurrentStockLedger;
use App\Services\Inventory\StoreManagement\RebaselineFreeze;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Historical invoice evidence only. No catalog price, refund spreading or stock mutation. */
final class MerchandiseReport
{
    public const EXPORT_FIELDS=['product_variant_id','sku','title','paid_units','returned_units','net_units','merchandise_minor_before_refunds','net_merchandise_minor','available','availability','stock_as_of'];
    private ?array $result=null;

    private function load(array $v): array
    {
        if($this->result!==null)return $this->result;
        $asOf=now()->utc();$start=CarbonImmutable::parse($v['from'],'Asia/Kuala_Lumpur')->utc();$end=CarbonImmutable::parse($v['to'],'Asia/Kuala_Lumpur')->addDay()->utc();
        $paid=DB::table('order_payments')->where('currency',$v['currency'])->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->whereNotNull('amount')->whereNotNull('confirmed_at')->where('confirmed_at','<=',$asOf)
            ->select('order_id')->selectRaw('SUM(amount) as confirmed_minor, MIN(confirmed_at) as first_confirmed_at')->groupBy('order_id');
        $orders=Order::query()->joinSub($paid,'paid','paid.order_id','=','orders.id')->where('orders.currency',$v['currency'])->where('orders.total','>',0)
            ->where('paid.first_confirmed_at','>=',$start)->where('paid.first_confirmed_at','<',$end)
            ->select('orders.*','paid.first_confirmed_at')->orderByDesc('paid.first_confirmed_at')->orderByDesc('orders.id')->limit(1001)->get();
        abort_if($orders->count()>1000,422,'Select a narrower period: at most 1,000 first-confirmed orders per merchandise report.');
        abort_if(DB::table('order_items')->whereIn('order_id',$orders->modelKeys())->count()>10000,422,'Select a narrower period: at most 10,000 item snapshots per merchandise report.');
        $orders->load('items');
        $coverage=['state'=>'partial','marketplaces'=>'Source not connected / not verified','incomplete_snapshots'=>0,'unallocated_refund_orders'=>0,'uncertain_payment_orders'=>0,
            'undated_confirmed_payments'=>DB::table('order_payments')->where('currency',$v['currency'])->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)->whereNull('confirmed_at')->count()];
        $rows=[];$trend=[];$itemCount=0;$search=mb_strtolower($v['search']??'');
        foreach($orders as $order){
            $day=CarbonImmutable::parse($order->first_confirmed_at,'UTC')->tz('Asia/Kuala_Lumpur')->toDateString();
            $uncertain=DB::table('order_payments')->where('order_id',$order->id)->whereIn('status',PaymentEvidence::CONFIRMED_STATUSES)
                ->where(fn($q)=>$q->whereNull('confirmed_at')->orWhereNull('amount')->orWhereNull('currency')->orWhere('currency','<>',$order->currency))->exists();
            if($uncertain){$coverage['uncertain_payment_orders']++;$trend[$day]=['day'=>$day,'merchandise_minor_before_refunds'=>null,'net_merchandise_minor'=>null];continue;}
            $itemCount+=$order->items->count();abort_if($itemCount>10000,422,'Select a narrower period: at most 10,000 item snapshots per merchandise report.');
            $lines=$this->validatedLines($order);
            if($lines===null){$coverage['incomplete_snapshots']++;$trend[$day]=['day'=>$day,'merchandise_minor_before_refunds'=>null,'net_merchandise_minor'=>null];continue;}
            $refunds=$this->fullLineRefunds($order,$lines,$asOf);
            if($refunds===null)$coverage['unallocated_refund_orders']++;
            foreach($order->items as $item){
                if($search!==''&&!str_contains(mb_strtolower($item->sku.' '.$item->title),$search))continue;
                $line=$lines[(string)$item->product_variant_id];$id=(int)$item->product_variant_id;
                $before=$line['net']-$line['rewards'];$returned=$refunds===null?null:($refunds[$item->id]['quantity']??0);
                $after=$returned===null?null:$before-($refunds[$item->id]['merchandise']??0);
                if(!isset($rows[$id]))$rows[$id]=['product_variant_id'=>$id,'sku'=>$item->sku,'title'=>$item->title,'paid_units'=>0,'returned_units'=>0,'net_units'=>0,'merchandise_minor_before_refunds'=>0,'net_merchandise_minor'=>0];
                $row=&$rows[$id];$row['paid_units']+=(int)$item->quantity;$row['merchandise_minor_before_refunds']+=$before;
                if($returned===null){$row['returned_units']=null;$row['net_units']=null;$row['net_merchandise_minor']=null;}
                else{if($row['returned_units']!==null)$row['returned_units']+=$returned;if($row['net_units']!==null)$row['net_units']+=(int)$item->quantity-$returned;if($row['net_merchandise_minor']!==null)$row['net_merchandise_minor']+=$after;}
                unset($row);
                if(!isset($trend[$day]))$trend[$day]=['day'=>$day,'merchandise_minor_before_refunds'=>0,'net_merchandise_minor'=>0];
                if($trend[$day]['merchandise_minor_before_refunds']!==null)$trend[$day]['merchandise_minor_before_refunds']+=$before;
                if($after===null)$trend[$day]['net_merchandise_minor']=null;elseif($trend[$day]['net_merchandise_minor']!==null)$trend[$day]['net_merchandise_minor']+=$after;
            }
        }
        $search=mb_strtolower($v['search']??'');
        $rows=array_values(array_filter($rows,fn($r)=>$search===''||str_contains(mb_strtolower($r['sku'].' '.$r['title']),$search)));
        foreach($rows as &$row)$row+=$this->stock($row['product_variant_id']);unset($row);
        usort($rows,fn($a,$b)=>($b['net_merchandise_minor']??-1)<=>($a['net_merchandise_minor']??-1)?:$a['product_variant_id']<=>$b['product_variant_id']);
        $incomplete=$coverage['incomplete_snapshots']+$coverage['unallocated_refund_orders']+$coverage['uncertain_payment_orders']+$coverage['undated_confirmed_payments']>0;
        $stats=['net_units'=>$incomplete?null:array_sum(array_column($rows,'net_units')),'net_merchandise_minor'=>$incomplete?null:array_sum(array_column($rows,'net_merchandise_minor')),'skus_sold'=>count($rows),'low_stock_skus'=>null,'stock_verified_skus'=>count(array_filter($rows,fn($r)=>$r['stock_verified'])),'stock_unverified_skus'=>count(array_filter($rows,fn($r)=>!$r['stock_verified']))];
        // Matching verified item snapshots drive table, KPI totals and trend; coverage flags describe the entire paid cohort.
        $daily=[];for($date=CarbonImmutable::parse($v['from']);$date->toDateString()<=$v['to'];$date=$date->addDay()){$key=$date->toDateString();$daily[]=$trend[$key]??['day'=>$key,'merchandise_minor_before_refunds'=>0,'net_merchandise_minor'=>0];}
        return $this->result=['rows'=>$rows,'stats'=>$stats,'trend'=>$daily,'coverage'=>$coverage,'as_of'=>$asOf->toIso8601String()];
    }

    private function validatedLines(Order $order): ?array
    {
        $snapshot=$order->promotion_snapshot;
        if(!is_array($snapshot)||($snapshot['version']??null)!==1||!is_array($snapshot['lines']??null)||!in_array($snapshot['type']??null,[null,'coupon','mix_match','addon','gift','tiered'],true)||$order->items->isEmpty())return null;
        $lines=$snapshot['lines'];if(count($lines)!==$order->items->count())return null;
        $totals=['gross'=>0,'discount'=>0,'tax'=>0,'rewards'=>0];$seen=[];
        foreach($order->items as $item){
            $key=(string)$item->product_variant_id;$line=$lines[$key]??null;
            if(!$item->product_variant_id||isset($seen[$key])||!is_array($line))return null;$seen[$key]=true;
            foreach(['quantity','gross','discount','net','tax','rewards'] as $field)if(!isset($line[$field])||!is_int($line[$field])||$line[$field]<0)return null;
            if($line['quantity']<1||$line['quantity']!==(int)$item->quantity||$line['gross']!==(int)$item->line_total||$line['gross']!==(int)$item->unit_price*(int)$item->quantity||$line['net']!==$line['gross']-$line['discount']||$line['rewards']>$line['net'])return null;
            foreach($totals as $field=>$_)$totals[$field]+=$line[$field];
        }
        if($totals['gross']!==(int)$order->subtotal||$totals['discount']!==(int)$order->discount_amount||$totals['tax']!==(int)$order->tax_amount||$totals['gross']-$totals['discount']+$totals['tax']+(int)$order->shipping_amount+(int)$order->payment_surcharge_amount-$totals['rewards']!==(int)$order->total)return null;
        return $lines;
    }

    private function fullLineRefunds(Order $order,array $lines,$asOf): ?array
    {
        $base=DB::table('order_refunds')->where('order_id',$order->id)->where('status','completed');
        if((clone $base)->whereNull('completed_at')->exists())return null;
        $allocated=[];
        foreach((clone $base)->where('completed_at','<=',$asOf)->orderBy('completed_at')->orderBy('id')->get() as $refund){
            $links=DB::table('return_requests')->where('resolved_refund_id',$refund->id)->where('order_id',$order->id)->where('status','resolved_refund')->where('resolution','refund')->whereNotNull('resolved_at')->where('resolved_at','<=',$asOf)->get();
            if($links->count()!==1)return null;$link=$links->first();$item=$order->items->firstWhere('id',$link->order_item_id);
            if(!$item)return null;
            $line=$lines[(string)$item->product_variant_id];$offset=$allocated[$item->id]['quantity']??0;$quantity=(int)$link->quantity;
            if($quantity<1||$offset+$quantity>(int)$item->quantity)return null;
            // Existing return service's historical minor-unit rule. Partial tax allocation is not evidenced.
            if($line['tax']!==0&&($offset!==0||$quantity!==(int)$item->quantity))return null;
            $expected=app(\App\Services\Promotions\OrderPromotionAmounts::class)->itemAmount($order,$item,$quantity,$offset);
            if($expected===null||(int)$refund->amount!==$expected||$expected<=0)return null;
            $merchandise=$line['tax']===0?$expected:$line['net']-$line['rewards'];
            $allocated[$item->id]=['quantity'=>$offset+$quantity,'merchandise'=>($allocated[$item->id]['merchandise']??0)+$merchandise];
        }
        return $allocated;
    }

    public function currentAvailability(int $variantId): array {return $this->stock($variantId);}

    private function stock(int $variantId): array
    {
        $empty=['available'=>null,'availability'=>'Availability not verified','stock_as_of'=>null,'stock_verified'=>false];
        if(app(RebaselineFreeze::class)->isPaused())return array_replace($empty,['availability'=>'Inventory rebaseline in progress']);
        $variant=\App\Models\ProductVariant::find($variantId);
        if(!$variant)return array_replace($empty,['availability'=>'Historical SKU no longer in catalog']);
        $quantity=app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($variant);
        if($quantity===null)return array_replace($empty,['availability'=>'First Count or catalog initialization required']);
        $mode=DB::table('inventory_configuration')->where('id',1)->value('operating_mode');
        $key=$mode==='shared_pool'?'shared':'store:website';
        $balance=DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')->where('p.pool_key',$key)->where('b.product_variant_id',$variantId)->select('b.*')->first();
        $verified=config('inventory.current_stock_only')&&$balance?->current_stock!==null&&$balance?->counted_at!==null&&(int)$balance->current_stock===$quantity;
        return ['available'=>$quantity,'availability'=>$verified?($quantity===0?'Out of stock':'Counted Current Stock'):'Website opening availability (unverified)',
            'stock_as_of'=>now()->toIso8601String(),'stock_verified'=>(bool)$verified];
    }

    public function orderedRows(string $type,array $v): Collection{return collect($this->load($v)['rows'])->map(fn($r)=>(object)$r);}
    public function query(string $type,array $v): array
    {
        $data=$this->load($v);$page=(int)($v['page']??1);$rows=$data['rows'];unset($data['rows']);
        return $data+['filters'=>$v+['timezone'=>'Asia/Kuala_Lumpur'],'records'=>new LengthAwarePaginator(array_slice($rows,($page-1)*20,20),count($rows),20,$page,['path'=>'/admin/api/reports/merchandise']),
            'basis'=>'Direct-store order cohort whose first dated confirmed payment is in this period/currency. A partial receipt establishes cohort membership, not full collection. Historical line snapshots supply discounts/rewards; tax, shipping and surcharges are excluded from merchandise. Exact item-linked completed refunds use the historical return amount rule: full lines or tax-free partial quantities with cumulative rounding. Tax-bearing partial, unlinked or inconsistent refund splits remain unavailable. Net units reflect those financial returns, never a stock restock. Current availability reuses the canonical website reader as-of now: counted Current Stock takes priority, with permitted opening Catalog availability explicitly unverified. It is not historical stock; unavailable initialization is never zero. Low-stock threshold and historical costs are not verified. Search filters SKU/title consistently across the table, KPI totals and trend; coverage warnings describe the full paid cohort. Marketplace coverage is not connected.'];
    }
}
