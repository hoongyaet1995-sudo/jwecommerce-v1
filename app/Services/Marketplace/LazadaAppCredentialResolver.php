<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use RuntimeException;

final class LazadaAppCredentialResolver
{
    /** @return array{key:string,secret:string} */
    public function production(): array
    {
        return $this->validated(config('marketplace.lazada.app_key'), config('marketplace.lazada.app_secret'));
    }

    /** @return array{key:string,secret:string} */
    public function officialTest(): array
    {
        return $this->validated(config('marketplace.lazada.test_app_key'), config('marketplace.lazada.test_app_secret'));
    }

    /** @return array{key:string,secret:string} */
    public function forStore(int $storeId): array
    {
        $token=MarketplaceStoreToken::where('store_id',$storeId)->first();
        if (!$token || !$token->application_id) throw new RuntimeException('Lazada store application credentials are unavailable.');
        $credentials=$token->credential_source==='official_test' ? $this->officialTest() : $this->production();
        if (!hash_equals($credentials['key'],(string)$token->application_id)) {
            throw new RuntimeException('Lazada application credentials changed. Re-authorize the store.');
        }
        return $credentials;
    }

    /** @return array{key:string,secret:string} */
    private function validated(mixed $key, mixed $secret): array
    {
        if (config('marketplace.lazada.enabled')!==true || !is_string($key)
            || !preg_match('/^[1-9][0-9]*$/D',$key) || !is_string($secret) || trim($secret)==='') {
            throw new RuntimeException('Lazada application credentials are not configured.');
        }
        return ['key'=>$key,'secret'=>$secret];
    }
}
