<?php

namespace App\Services\Marketplace;

use RuntimeException;

final class LazadaTokenResponse
{
    /** R1 supports Malaysian local sellers; tokens are internal-only, never API resources. */
    public static function validate(#[\SensitiveParameter] array $payload): array
    {
        $stores=$payload['country_user_info'] ?? null;
        if (!in_array($payload['code'] ?? null, [0,'0'],true)
            || !is_string($payload['access_token'] ?? null) || trim($payload['access_token'])===''
            || !is_string($payload['refresh_token'] ?? null) || trim($payload['refresh_token'])===''
            || !is_int($payload['expires_in'] ?? null) || $payload['expires_in']<=0
            || !is_int($payload['refresh_expires_in'] ?? null) || $payload['refresh_expires_in']<=0
            || ($payload['country'] ?? null)!=='my' || ($payload['account_platform'] ?? null)!=='seller_center'
            || !is_array($stores) || !array_is_list($stores) || count($stores)!==1
            || !is_array($stores[0]) || ($stores[0]['country'] ?? null)!=='my') {
            throw new RuntimeException('Lazada authorization response is incomplete or unsupported.');
        }
        $seller=$stores[0]['seller_id'] ?? null;
        if ((!is_string($seller) && !is_int($seller)) || !preg_match('/^[1-9][0-9]*$/D',(string)$seller)) {
            throw new RuntimeException('Lazada authorization response is incomplete or unsupported.');
        }
        return ['seller_id'=>(string)$seller,'region'=>'my',
            'access_token'=>$payload['access_token'],'refresh_token'=>$payload['refresh_token'],
            'expires_in'=>$payload['expires_in'],'refresh_expires_in'=>$payload['refresh_expires_in']];
    }
}
