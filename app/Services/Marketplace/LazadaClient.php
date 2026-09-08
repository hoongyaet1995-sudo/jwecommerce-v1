<?php

namespace App\Services\Marketplace;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class LazadaClient
{
    /**
     * Submit a category-complete Lazada product payload after the operator has
     * reviewed the dynamic category attributes and Lazada-hosted images.
     * This method never invents required marketplace attributes.
     */
    public function createProduct(#[\SensitiveParameter] string $accessToken, array $payload): array
    {
        if (config('marketplace.publish_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace publishing is disabled.');
        }
        $product=$payload['Request']['Product'] ?? null;
        if (count($payload)!==1 || !is_array($product) || !preg_match('/^[1-9][0-9]*$/D',(string)($product['PrimaryCategory'] ?? ''))
            || !is_array($product['Images']['Image'] ?? null) || !$product['Images']['Image']
            || !is_array($product['Attributes'] ?? null) || !is_string($product['Attributes']['name'] ?? null)
            || trim($product['Attributes']['name'])==='' || !is_array($product['Skus']['Sku'] ?? null)
            || !$product['Skus']['Sku'] || !array_is_list($product['Skus']['Sku'])) {
            throw new RuntimeException('Invalid Lazada product creation payload.');
        }
        $sellerSkus=[];
        foreach ($product['Images']['Image'] as $image) {
            if (!is_string($image) || !preg_match('#\Ahttps://[A-Za-z0-9.-]+\.slatic\.net/#D',$image)) {
                throw new RuntimeException('Lazada product images must be Lazada-hosted HTTPS URLs.');
            }
        }
        foreach ($product['Skus']['Sku'] as $sku) {
            $sellerSku=is_array($sku) ? ($sku['SellerSku'] ?? null) : null;
            $quantity=is_array($sku) ? ($sku['quantity'] ?? null) : null;
            $price=is_array($sku) ? ($sku['price'] ?? ($sku['supply_price'] ?? null)) : null;
            if (!is_string($sellerSku) || !preg_match('/\A[ -~]{1,200}\z/D',$sellerSku) || isset($sellerSkus[$sellerSku])
                || !is_numeric($quantity) || (int)$quantity<0 || !is_numeric($price) || (float)$price<=0) {
                throw new RuntimeException('Invalid Lazada product SKU payload.');
            }
            $sellerSkus[$sellerSku]=true;
        }
        $result=$this->signedPost('/product/create',$accessToken,$payload,'Lazada product creation');
        $data=$result['data'] ?? null;
        if (!is_array($data) || !preg_match('/^[1-9][0-9]*$/D',(string)($data['item_id'] ?? ''))
            || !is_array($data['sku_list'] ?? null) || !array_is_list($data['sku_list'])) {
            throw new PublishingResultUncertain('Lazada product creation was not confirmed. Reconcile before retrying.');
        }
        $ids=[];
        foreach ($data['sku_list'] as $sku) {
            if (!is_array($sku) || !is_string($sku['seller_sku'] ?? null) || !isset($sellerSkus[$sku['seller_sku']])
                || !preg_match('/^[1-9][0-9]*$/D',(string)($sku['sku_id'] ?? '')) || isset($ids[$sku['seller_sku']])) {
                throw new PublishingResultUncertain('Lazada product creation was not confirmed. Reconcile before retrying.');
            }
            $ids[$sku['seller_sku']]=(string)$sku['sku_id'];
        }
        if (count($ids)!==count($sellerSkus)) {
            throw new PublishingResultUncertain('Lazada product creation was not confirmed. Reconcile before retrying.');
        }
        return ['item_id'=>(string)$data['item_id'],'sku_ids'=>$ids];
    }

    /**
     * Transport for one reviewed absolute SKU quantity. Callers must revalidate
     * their WMS snapshot immediately before invoking it.
     */
    public function updateAbsoluteQuantity(#[\SensitiveParameter] string $accessToken, string $itemId, string $skuId, int $quantity): void
    {
        if (config('marketplace.stock_sync_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace stock synchronization is disabled.');
        }
        if (!preg_match('/^[1-9][0-9]*$/D', $itemId) || !preg_match('/^[1-9][0-9]*$/D', $skuId) || $quantity < 0) {
            throw new RuntimeException('Invalid Lazada stock synchronization payload.');
        }
        $key=config('marketplace.lazada.app_key');$secret=config('marketplace.lazada.app_secret');
        if(config('marketplace.lazada.enabled')!==true||!is_string($key)||!preg_match('/^[1-9][0-9]*$/D',$key)
            ||!is_string($secret)||trim($secret)===''||trim($accessToken)==='')throw new RuntimeException('Lazada stock synchronization is not configured.');
        $path='/product/price_quantity/update';
        $query=['app_key'=>$key,'access_token'=>$accessToken,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
        ksort($query,SORT_STRING);$base=$path;
        foreach($query as $name=>$value)$base.=$name.$value;
        $query['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
        $payload=['Request'=>['Product'=>['Skus'=>['Sku'=>['ItemId'=>$itemId,'SkuId'=>$skuId,'Quantity'=>(string)$quantity]]]]];
        try {$response=Http::acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->post('https://api.lazada.com.my/rest'.$path.'?'.http_build_query($query),$payload);}
        catch(ConnectionException){throw new RuntimeException('Lazada stock synchronization result is unknown. Reconcile before retrying.');}
        $result=$response->json();
        if(!$response->successful()||!is_array($result)||!in_array($result['code']??null,['0',0],true)) {
            throw new RuntimeException('Lazada stock synchronization was not confirmed. Reconcile before retrying.');
        }
    }

    private function signedPost(string $path, #[\SensitiveParameter] string $accessToken, array $payload, string $operation): array
    {
        $key=config('marketplace.lazada.app_key');$secret=config('marketplace.lazada.app_secret');
        if(config('marketplace.lazada.enabled')!==true||!is_string($key)||!preg_match('/^[1-9][0-9]*$/D',$key)
            ||!is_string($secret)||trim($secret)===''||trim($accessToken)==='')throw new RuntimeException($operation.' is not configured.');
        $query=['app_key'=>$key,'access_token'=>$accessToken,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
        ksort($query,SORT_STRING);$base=$path;
        foreach($query as $name=>$value)$base.=$name.$value;
        $query['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
        try {$response=Http::acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->post('https://api.lazada.com.my/rest'.$path.'?'.http_build_query($query),$payload);}
        catch(ConnectionException){throw new PublishingResultUncertain($operation.' result is unknown. Reconcile before retrying.');}
        $result=$response->json();
        if(!$response->successful()||!is_array($result)||!in_array($result['code']??null,['0',0],true)) {
            throw new PublishingResultUncertain($operation.' was not confirmed. Reconcile before retrying.');
        }
        return $result;
    }

    /** Malaysia seller read APIs only. Tokens and signed URLs never leave this service. */
    public function read(string $path, #[\SensitiveParameter] string $accessToken, array $parameters=[]): array
    {
        if(!in_array($path,['/orders/get','/order/get','/order/items/get','/orders/items/get','/products/get','/product/item/get'],true)) {
            throw new RuntimeException('Unsupported Lazada read endpoint.');
        }
        $key=config('marketplace.lazada.app_key');$secret=config('marketplace.lazada.app_secret');
        if(config('marketplace.lazada.enabled')!==true||!is_string($key)||!preg_match('/^[1-9][0-9]*$/D',$key)
            ||!is_string($secret)||trim($secret)===''||trim($accessToken)==='')throw new RuntimeException('Lazada read access is not configured.');
        if(array_intersect(array_keys($parameters),['app_key','access_token','sign','sign_method','timestamp']))throw new RuntimeException('Lazada authentication fields cannot be overridden.');
        foreach($parameters as $name=>$value){
            if(!is_string($name)||!preg_match('/^[a-z][a-z0-9_]*$/D',$name)||(!is_string($value)&&!is_int($value)))throw new RuntimeException('Invalid Lazada read parameters.');
        }
        $query=$parameters+['app_key'=>$key,'access_token'=>$accessToken,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
        ksort($query,SORT_STRING);$base=$path;
        foreach($query as $name=>$value)$base.=$name.$value;
        $query['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
        try{
            $response=Http::acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->get('https://api.lazada.com.my/rest'.$path,$query);
        }catch(ConnectionException){throw new RuntimeException('Lazada read request could not be completed.');}
        $payload=$response->json();
        if(!$response->successful()||!is_array($payload)||!in_array($payload['code']??null,['0',0],true)
            ||!is_array($payload['data']??null))throw new RuntimeException('Lazada read request failed or returned an incomplete response.');
        return $payload['data'];
    }
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
