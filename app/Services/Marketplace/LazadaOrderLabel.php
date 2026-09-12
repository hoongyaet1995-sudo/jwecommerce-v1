<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaOrderLabel
{
    public function __construct(private LazadaClient $client,private LazadaTokenManager $tokens,private LazadaLabelPdf $pdf) {}

    public function download(int $orderId,string $packageId):string
    {
        if (!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$packageId)) throw new RuntimeException('Invalid package identity.');
        $order=DB::table('marketplace_orders')->find($orderId);
        $store=$order?DB::table('marketplace_stores')->find($order->store_id):null;
        if (!$store||$store->platform!=='lazada'||$store->status!=='connected'||$store->region!=='MY'||$store->environment!=='live') {
            throw new RuntimeException('Connected Malaysia Lazada order required.');
        }
        $token=$this->tokens->accessToken($store->id);
        $items=$this->client->read('/order/items/get',$token,['order_id'=>$order->external_order_id]);
        if (!array_is_list($items)||!$items) throw new RuntimeException('Order items unavailable.');
        $matched=false;$seen=[];
        foreach ($items as $item) {
            $id=(string)($item['order_item_id']??'');
            if ((string)($item['order_id']??'')!==$order->external_order_id||!preg_match('/\A[1-9][0-9]*\z/D',$id)||isset($seen[$id])) throw new RuntimeException('Order identity could not be verified.');
            $seen[$id]=true;
            if (($item['package_id']??null)!==$packageId) continue;
            if (!in_array($item['status']??null,['packed','ready_to_ship','ready_to_ship_pending','shipped','delivered'],true)
                ||!in_array($item['delivery_option_sof']??null,[0,'0'],true)||!in_array($item['is_digital']??null,[0,'0'],true)
                ||!in_array($item['is_fbl']??null,[0,'0'],true)) throw new RuntimeException('Package is not eligible for this label workflow.');
            $matched=true;
        }
        if (!$matched) throw new RuntimeException('Package does not belong to this order.');
        $document=$this->client->read('/order/package/document/get',$token,['getDocumentReq'=>json_encode([
            'doc_type'=>'PDF','packages'=>[['package_id'=>$packageId]],
        ],JSON_THROW_ON_ERROR)]);
        return $this->pdf->download($document);
    }
}
