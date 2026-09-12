<?php
namespace App\Services\Marketplace;
use RuntimeException;

final class LazadaRtsResult
{
    public static function parse(array $body,array $expected):array
    {
        $fail=static fn()=>new RuntimeException('RTS outcome is incomplete. Reconcile before retrying.');
        if (!$expected||!array_is_list($expected)||count($expected)>20||count($expected)!==count(array_unique($expected))) throw $fail();
        foreach ($expected as $id) if (!is_string($id)||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$id)) throw $fail();
        $rows=$body['result']['data']['packages']??null;
        if (!in_array($body['code']??null,[0,'0'],true)||($body['result']['success']??null)!==true||!is_array($rows)||!array_is_list($rows)) throw $fail();
        $packages=[];$complete=true;
        foreach ($rows as $row) {
            $id=$row['package_id']??null;$code=$row['item_err_code']??null;
            if (!is_string($id)||!in_array($id,$expected,true)||isset($packages[$id])||(!is_string($code)&&!is_int($code))
                ||!preg_match('/\A[A-Za-z0-9_.-]{1,40}\z/D',(string)$code)) throw $fail();
            $success=(string)$code==='0';$complete=$complete&&$success;
            $packages[$id]=['status'=>$success?'ready_to_ship':'unconfirmed','code'=>(string)$code];
        }
        if (count($packages)!==count($expected)) throw $fail();
        return ['complete'=>$complete,'packages'=>$packages];
    }
}
