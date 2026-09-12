<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/** Transport only: caller must review fresh item states and provider allocation before sending. */
final class LazadaPackingClient
{
    public function pack(#[\SensitiveParameter] string $token,string $orderId,array $itemIds,string $allocationType,?string $providerId=null):array
    {
        if (config('marketplace.fulfillment_operations_enabled')!==true) throw new RuntimeException('Marketplace fulfillment is disabled.');
        $key=config('marketplace.lazada.app_key'); $secret=config('marketplace.lazada.app_secret');
        if (config('marketplace.lazada.enabled')!==true||!is_string($key)||!preg_match('/^[1-9][0-9]*$/D',$key)
            ||!is_string($secret)||trim($secret)===''||trim($token)==='') throw new RuntimeException('Lazada fulfillment is not configured.');
        if (!preg_match('/^[1-9][0-9]{0,29}$/D',$orderId)||!$itemIds||!array_is_list($itemIds)
            ||count($itemIds)!==count(array_unique($itemIds))||!in_array($allocationType,['TFS','NTFS'],true)) throw new RuntimeException('Invalid packing selection.');
        foreach ($itemIds as $id) if (!is_string($id)||!preg_match('/^[1-9][0-9]{0,29}$/D',$id)) throw new RuntimeException('Invalid packing selection.');
        if ($providerId!==null&&!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$providerId)) throw new RuntimeException('Invalid shipment provider.');
        $payload=['pack_order_list'=>[['order_id'=>$orderId,'order_item_list'=>$itemIds]],'delivery_type'=>'dropship','shipping_allocate_type'=>$allocationType];
        if ($providerId!==null) $payload['shipment_provider_id']=$providerId;
        $path='/order/fulfill/pack';
        $parameters=['packReq'=>json_encode($payload,JSON_THROW_ON_ERROR),'app_key'=>$key,'access_token'=>$token,
            'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
        ksort($parameters,SORT_STRING); $base=$path;
        foreach ($parameters as $name=>$value) $base.=$name.$value;
        $parameters['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
        try {
            $response=Http::asForm()->acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()
                ->post('https://api.lazada.com.my/rest'.$path,$parameters);
        } catch (ConnectionException) {
            throw new RuntimeException('Lazada packing outcome is unknown. Reconcile before retrying.');
        }
        $body=$response->json();
        if (!$response->successful()||!is_array($body)) throw new RuntimeException('Lazada packing outcome is unknown. Reconcile before retrying.');
        return LazadaPackResult::parse($body,$orderId,$itemIds);
    }
}
