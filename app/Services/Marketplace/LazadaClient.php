<?php

namespace App\Services\Marketplace;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class LazadaClient
{
    public function isAuthorizationReady(): bool
    {
        if (config('marketplace.lazada.enabled')!==true || !is_string(config('marketplace.lazada.app_secret'))
            || trim(config('marketplace.lazada.app_secret'))==='') return false;
        try {
            LazadaAuthorizationUrl::build((string)config('marketplace.lazada.app_key'),
                (string)config('marketplace.lazada.callback_url'),config('app.url'),str_repeat('0',64));
            return true;
        } catch (RuntimeException) { return false; }
    }

    public function exchange(#[\SensitiveParameter] string $code): array
    {
        return $this->tokenRequest('/auth/token/create','code',$code);
    }

    public function refresh(#[\SensitiveParameter] string $refreshToken): array
    {
        return $this->tokenRequest('/auth/token/refresh','refresh_token',$refreshToken);
    }

    private function tokenRequest(string $path, string $field, #[\SensitiveParameter] string $value): array
    {
        $key=config('marketplace.lazada.app_key');
        $secret=config('marketplace.lazada.app_secret');
        if (config('marketplace.lazada.enabled')!==true || !is_string($key) || !preg_match('/^[1-9][0-9]*$/D',$key)
            || !is_string($secret) || trim($secret)==='' || trim($value)==='') {
            throw new RuntimeException('Lazada authorization is not configured.');
        }
        $query=['app_key'=>$key,$field=>$value,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
        ksort($query,SORT_STRING);
        $base=$path;
        foreach ($query as $name=>$parameter) $base.=$name.$parameter;
        $query['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
        try {
            // No automatic retries: an authorization code may already have been consumed.
            $response=Http::acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()
                ->get('https://auth.lazada.com/rest'.$path,$query);
        } catch (ConnectionException) {
            // Do not chain exceptions containing the credential-bearing request URL.
            throw new RuntimeException('Lazada token request failed. Start authorization again or retry later.');
        }
        $payload=$response->json();
        if (!$response->successful() || !is_array($payload)) {
            throw new RuntimeException('Lazada token request failed. Start authorization again or retry later.');
        }
        return LazadaTokenResponse::validate($payload);
    }
}
