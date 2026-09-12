<?php
namespace App\Services\Marketplace;

final class LazadaOrderCurrency
{
    public static function fromPayload(array $payload):?string
    {
        $valid=static fn ($value)=>is_string($value)&&preg_match('/^[A-Z]{3}$/D',$value);
        if ($valid($payload['currency']??null)) return $payload['currency'];
        $items=$payload['order_items']??null;
        if (!is_array($items)||!array_is_list($items)||!$items) return null;
        $currency=null;
        foreach ($items as $item) {
            $value=is_array($item)?($item['currency']??null):null;
            if (!$valid($value)||($currency!==null&&$currency!==$value)) return null;
            $currency=$value;
        }
        return $currency;
    }
}
