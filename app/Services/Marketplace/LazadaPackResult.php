<?php
namespace App\Services\Marketplace;

/** One-order pack response; a top-level success is not proof that every item packed. */
final class LazadaPackResult
{
    public static function parse(array $body,string $orderId,array $expectedItems):array
    {
        $fail=static fn()=>throw new \RuntimeException('Lazada packing result is incomplete. Reconcile before retrying.');
        if (!$expectedItems||!array_is_list($expectedItems)||count($expectedItems)!==count(array_unique($expectedItems))) $fail();
        foreach ($expectedItems as $id) if (!is_string($id)||!preg_match('/^[1-9][0-9]*$/D',$id)) $fail();
        $orders=$body['result']['data']['pack_order_list']??null;
        if (!in_array($body['code']??null,[0,'0'],true)||($body['result']['success']??null)!==true
            ||!is_array($orders)||!array_is_list($orders)||count($orders)!==1
            ||(string)($orders[0]['order_id']??'')!==$orderId) $fail();
        $lines=$orders[0]['order_item_list']??null;
        if (!is_array($lines)||!array_is_list($lines)) $fail();
        $items=[]; $complete=true;
        foreach ($lines as $line) {
            if (!is_array($line)) $fail();
            $id=(string)($line['order_item_id']??'');
            $code=$line['item_err_code']??null;
            if (!in_array($id,$expectedItems,true)||isset($items[$id])||(!is_int($code)&&!is_string($code))
                ||!preg_match('/^[A-Za-z0-9_.-]{1,40}$/D',(string)$code)) $fail();
            $packed=in_array($code,[0,'0'],true);
            $package=$line['package_id']??null;
            if ($packed&&(!is_string($package)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$package))) $fail();
            $items[$id]=['status'=>$packed?'packed':'unconfirmed','code'=>(string)$code,
                'package_id'=>$packed?$package:null,
                'tracking_number'=>$packed&&is_string($line['tracking_number']??null)?$line['tracking_number']:null];
            $complete=$complete&&$packed;
        }
        if (count($items)!==count($expectedItems)) $fail();
        return ['complete'=>$complete,'items'=>$items];
    }
}
