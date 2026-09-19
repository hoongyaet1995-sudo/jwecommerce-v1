<?php
namespace App\Services\Marketplace;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Read-only Lazada order pull. Order-item inventory transitions remain disabled. */
final class LazadaOrderService
{
    public function __construct(private LazadaClient $client, private LazadaTokenManager $tokens,
        private MarketplaceOrderSnapshotService $snapshots) {}

    public function refreshOrder(int $localOrderId):int
    {
        $order=DB::table('marketplace_orders')->find($localOrderId);
        $store=$order?DB::table('marketplace_stores')->find($order->store_id):null;
        if (!$store||$store->platform!=='lazada'||$store->status!=='connected'||$store->region!=='MY'||$store->environment!=='live') throw new RuntimeException('Connected Lazada order required.');
        $token=$this->tokens->accessToken($store->id);
        $summary=$this->client->read('/order/get',$token,['order_id'=>$order->external_order_id],(int)$store->id);
        if ((string)($summary['order_id']??'')!==$order->external_order_id) throw new RuntimeException('Order identity mismatch.');
        $items=$this->client->read('/order/items/get',$token,['order_id'=>$order->external_order_id],(int)$store->id);
        if (!$items||!array_is_list($items)) throw new RuntimeException('Complete order items required.');
        $seen=[];
        foreach ($items as $item) {
            $id=(string)($item['order_item_id']??'');
            if (!preg_match('/\A[1-9][0-9]*\z/D',$id)||isset($seen[$id])||(string)($item['order_id']??'')!==$order->external_order_id) throw new RuntimeException('Order item identity mismatch.');
            $seen[$id]=true;
        }
        return $this->snapshots->saveLazada($store->id,array_replace($summary,['order_items'=>$items]));
    }

    public function pullPage(int $storeId, int $from, int $to, int $offset=0): array
    {
        if($from<0||$to<=$from||$offset<0||$offset>5000||$offset%100!==0)throw new RuntimeException('Invalid Lazada order page window.');
        $token=$this->tokens->accessToken($storeId);
        $store=DB::table('marketplace_stores')->find($storeId);
        if(!$store||$store->platform!=='lazada'||$store->status!=='connected')throw new RuntimeException('Lazada store is not connected.');
        $format=fn(int $time)=>CarbonImmutable::createFromTimestampUTC($time)->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d\\TH:i:sP');
        $page=$this->client->read('/orders/get',$token,['created_after'=>$format($from),'created_before'=>$format($to),'status'=>'all','limit'=>100,'offset'=>$offset],$storeId);
        $orders=$page['orders']??null;
        if(!is_array($orders)||!array_is_list($orders)||count($orders)>100)throw new RuntimeException('Lazada order page is incomplete.');
        $saved=0;
        foreach($orders as $summary){
            if(!is_array($summary)||(!is_string($summary['order_id']??null)&&!is_int($summary['order_id']??null)))throw new RuntimeException('Lazada order page contains an invalid order identity.');
            $details=$this->client->read('/order/items/get',$token,['order_id'=>(string)$summary['order_id']],$storeId);
            $items=array_is_list($details)?$details:($details['order_items']??null);
            if(!is_array($items)||!array_is_list($items))throw new RuntimeException('Lazada order items are incomplete.');
            $seen=[];
            foreach($items as $item){
                $itemId=is_array($item)?($item['order_item_id']??null):null;
                if(!is_array($item)||(!is_string($itemId)&&!is_int($itemId))||(string)$itemId===''
                    ||(string)($item['order_id']??'')!==(string)$summary['order_id']||isset($seen[(string)$itemId])) {
                    throw new RuntimeException('Lazada order item identity is missing, duplicated or belongs to another order.');
                }
                $seen[(string)$itemId]=true;
            }
            $this->snapshots->saveLazada($storeId,array_replace($summary,['order_items'=>$items]));
            $saved++;
        }
        $countTotal=$page['countTotal']??null;
        if(!is_int($countTotal)&&!ctype_digit((string)$countTotal))throw new RuntimeException('Lazada order page count is incomplete.');
        // Lazada documents offsets as 0/100/200 with a 100-row limit. Advance
        // by the requested page size, not a short response, so a transiently
        // short page cannot create an invalid offset or duplicate a window.
        $hasMore=$offset+$saved<(int)$countTotal;
        return ['orders'=>$saved,'next_offset'=>$offset+100,'has_more'=>$hasMore,'count_total'=>(int)$countTotal];
    }
}
