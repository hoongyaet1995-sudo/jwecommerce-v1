<?php

namespace App\Services\Marketplace;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class LazadaClient
{
    /** Transport only; callers must resolve a reviewed, owned product image first. */
    public function migrateImage(#[\SensitiveParameter] string $accessToken, string $sourceUrl, ?int $storeId=null): string
    {
        if (config('marketplace.publish_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace image migration is disabled.');
        }
        $source=parse_url($sourceUrl);
        if (!filter_var($sourceUrl,FILTER_VALIDATE_URL) || ($source['scheme'] ?? null)!=='https'
            || isset($source['user']) || isset($source['pass']) || isset($source['fragment'])
            || isset($source['port']) || !str_contains($source['host'] ?? '', '.')
            || filter_var($source['host'] ?? '',FILTER_VALIDATE_IP)) {
            throw new RuntimeException('A public HTTPS product image URL is required.');
        }
        $result=$this->signedPost('/image/migrate',$accessToken,['Request'=>['Image'=>['Url'=>$sourceUrl]]],'Lazada image migration',$storeId);
        $url=$result['data']['image']['url'] ?? null;
        if (!is_string($url) || !preg_match('#\Ahttps://[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.slatic\.net/[^\s]+\z#D',$url)
            || !filter_var($url,FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Lazada image migration returned no confirmed platform image.');
        }
        return $url;
    }

    /**
     * Submit a category-complete Lazada product payload after the operator has
     * reviewed the dynamic category attributes and Lazada-hosted images.
     * This method never invents required marketplace attributes.
     */
    public function createProduct(#[\SensitiveParameter] string $accessToken, array $payload, ?int $storeId=null): array
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
                || (!is_int($quantity)&&!is_string($quantity)) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/D',(string)$quantity)
                || filter_var($quantity,FILTER_VALIDATE_INT)===false
                || !is_numeric($price) || !is_finite((float)$price) || (float)$price<=0) {
                throw new RuntimeException('Invalid Lazada product SKU payload.');
            }
            $sellerSkus[$sellerSku]=true;
        }
        $result=$this->signedPost('/product/create',$accessToken,$payload,'Lazada product creation',$storeId);
        $data=$result['data'] ?? null;
        if (!is_array($data) || !preg_match('/^[1-9][0-9]*$/D',(string)($data['item_id'] ?? ''))
            || !is_array($data['sku_list'] ?? null) || !array_is_list($data['sku_list'])) {
            throw new PublishingResultUncertain('Lazada product creation was not confirmed. Reconcile before retrying.');
        }
        $ids=[];
        foreach ($data['sku_list'] as $sku) {
            if (!is_array($sku) || !is_string($sku['seller_sku'] ?? null) || !isset($sellerSkus[$sku['seller_sku']])
                || !preg_match('/^[1-9][0-9]*$/D',(string)($sku['sku_id'] ?? '')) || isset($ids[$sku['seller_sku']])
                || in_array((string)$sku['sku_id'],$ids,true)) {
                throw new PublishingResultUncertain('Lazada product creation was not confirmed. Reconcile before retrying.');
            }
            $ids[$sku['seller_sku']]=(string)$sku['sku_id'];
        }
        if (count($ids)!==count($sellerSkus)) {
            throw new PublishingResultUncertain('Lazada product creation was not confirmed. Reconcile before retrying.');
        }
        return ['item_id'=>(string)$data['item_id'],'sku_ids'=>$ids];
    }

    /** Attach one reviewed Lazada-hosted size-chart image after product creation. */
    public function updateSizeChart(#[\SensitiveParameter] string $accessToken, string $itemId, string $sizeChart, ?int $storeId=null): void
    {
        if (config('marketplace.publish_operations_enabled')!==true) {
            throw new RuntimeException('Marketplace publishing is disabled.');
        }
        if (!preg_match('/^[1-9][0-9]*$/D',$itemId)
            || !preg_match('#\Ahttps://[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.slatic\.net/[^\s]+\z#D',$sizeChart)) {
            throw new RuntimeException('Invalid Lazada size chart selection.');
        }
        $this->signedPost('/size/chart/batch/update',$accessToken,['Request'=>['Product'=>['SizeCharts'=>['SizeChart'=>[
            ['product_id'=>$itemId,'size_chart'=>$sizeChart],
        ]]]]],'Lazada size chart update',$storeId);
    }

    /**
     * Transport for one reviewed absolute SKU quantity. Callers must revalidate
     * their WMS snapshot immediately before invoking it.
     */
    public function updateAbsoluteQuantity(#[\SensitiveParameter] string $accessToken, string $itemId, string $skuId, int $quantity, ?string $warehouseCode = null, ?int $storeId=null): void
    {
        if (config('marketplace.stock_sync_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace stock synchronization is disabled.');
        }
        $this->sendAbsoluteQuantity($accessToken,$itemId,$skuId,$quantity,$warehouseCode,$storeId);
    }

    /** Separate test boundary; does not enable the production transport flag. */
    public function updateSandboxQuantity(array $snapshot, \App\Models\User $actor):void
    {
        $current=app(SandboxInventoryWriteGuard::class)->review((int)$snapshot['store_id'],(int)$snapshot['inventory_balance_id'],$actor);
        if($current!==$snapshot||$current['platform']!=='lazada')throw new RuntimeException('Sandbox stock review changed.');
        $token=app(LazadaTokenManager::class)->accessToken($current['store_id']);
        $seller=$this->read('/seller/get',$token,[],$current['store_id']);
        if((string)($seller['seller_id']??'')!==$current['external_shop_id']||($seller['short_code']??'')!=='MY4NA1T7CK'||($seller['status']??'')!=='ACTIVE')throw new RuntimeException('Sandbox seller identity changed.');
        $this->sendAbsoluteQuantity($token,$current['external_product_id'],$current['external_variant_id'],$current['desired_quantity'],$current['platform_location_id'],$current['store_id']);
    }

    private function sendAbsoluteQuantity(#[\SensitiveParameter] string $accessToken,string $itemId,string $skuId,int $quantity,?string $warehouseCode,?int $storeId=null):void
    {
        if (!preg_match('/^[1-9][0-9]*$/D', $itemId) || !preg_match('/^[1-9][0-9]*$/D', $skuId) || $quantity < 0) {
            throw new RuntimeException('Invalid Lazada stock synchronization payload.');
        }
        $credentials=$this->credentials($storeId);$key=$credentials['key'];$secret=$credentials['secret'];
        if(trim($accessToken)==='')throw new RuntimeException('Lazada stock synchronization is not configured.');
        if ($warehouseCode !== null && !preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D', $warehouseCode)) {
            throw new RuntimeException('Invalid Lazada warehouse code.');
        }
        $stock='<SellableQuantity>'.$quantity.'</SellableQuantity>';
        if ($warehouseCode !== null) {
            $stock='<MultiWarehouseInventories><MultiWarehouseInventory><WarehouseCode>'.$warehouseCode
                .'</WarehouseCode>'.$stock.'</MultiWarehouseInventory></MultiWarehouseInventories>';
        }
        $payload='<Request><Product><Skus><Sku><ItemId>'.$itemId.'</ItemId><SkuId>'.$skuId
            .'</SkuId>'.$stock.'</Sku></Skus></Product></Request>';
        $path='/product/stock/sellable/update';
        $query=['payload'=>$payload,'app_key'=>$key,'access_token'=>$accessToken,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
        ksort($query,SORT_STRING);$base=$path;
        foreach($query as $name=>$value)$base.=$name.$value;
        $query['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
        try {$response=Http::asForm()->acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->post('https://api.lazada.com.my/rest'.$path,$query);}
        catch(ConnectionException){throw new RuntimeException('Lazada stock synchronization result is unknown. Reconcile before retrying.');}
        $result=$response->json();
        if(!$response->successful()||!is_array($result)||!in_array($result['code']??null,['0',0],true)) {
            throw new RuntimeException('Lazada stock synchronization was not confirmed. Reconcile before retrying.');
        }
    }

    private function signedPost(string $path, #[\SensitiveParameter] string $accessToken, array $payload, string $operation, ?int $storeId=null): array
    {
        $credentials=$this->credentials($storeId);$key=$credentials['key'];$secret=$credentials['secret'];
        if(trim($accessToken)==='')throw new RuntimeException($operation.' is not configured.');
        $query=['payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'app_key'=>$key,'access_token'=>$accessToken,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
        ksort($query,SORT_STRING);$base=$path;
        foreach($query as $name=>$value)$base.=$name.$value;
        $query['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
        try {$response=Http::asForm()->acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->post('https://api.lazada.com.my/rest'.$path,$query);}
        catch(ConnectionException){throw new PublishingResultUncertain($operation.' result is unknown. Reconcile before retrying.');}
        $result=$response->json();
        if($response->successful() && is_array($result) && !in_array($result['code']??null,['0',0],true)) {
            $requestId=is_array($result)?($result['request_id']??null):null;
            if ($requestId===$accessToken || $requestId===$secret) $requestId=null;
            throw new PublishingProviderRejected($operation.' was rejected by Lazada.',[
                'http_status'=>$response->status(),'provider_code'=>is_array($result)?($result['code']??null):null,
                'request_id'=>$requestId,
            ]);
        }
        if(!$response->successful()||!is_array($result)) {
            throw new PublishingResultUncertain($operation.' was not confirmed. Reconcile before retrying.');
        }
        return $result;
    }

    /** Malaysia seller read APIs only. Tokens and signed URLs never leave this service. */
    public function read(string $path, #[\SensitiveParameter] string $accessToken, array $parameters=[], ?int $storeId=null): array
    {
        return $this->readWithCredentials($path,$accessToken,$parameters,$this->credentials($storeId));
    }

    public function readForStore(int $storeId, string $path, #[\SensitiveParameter] string $accessToken, array $parameters=[]): array
    {
        return $this->read($path,$accessToken,$parameters,$storeId);
    }

    public function readOfficialTest(string $path, #[\SensitiveParameter] string $accessToken, array $parameters=[]): array
    {
        return $this->readWithCredentials($path,$accessToken,$parameters,app(LazadaAppCredentialResolver::class)->officialTest());
    }

    /** @param array{key:string,secret:string} $credentials */
    private function readWithCredentials(string $path, #[\SensitiveParameter] string $accessToken, array $parameters, array $credentials): array
    {
        if(!in_array($path,['/seller/warehouse/get','/category/brands/query','/order/package/document/get','/order/shipment/providers/get','/category/tree/get','/category/attributes/get','/rc/warehouse/get','/rc/warehouse/detail/get','/seller/get','/orders/get','/order/get','/order/items/get','/orders/items/get','/products/get','/product/item/get'],true)) {
            throw new RuntimeException('Unsupported Lazada read endpoint.');
        }
        $key=$credentials['key'];$secret=$credentials['secret'];
        if(trim($accessToken)==='')throw new RuntimeException('Lazada read access is not configured.');
        if(array_intersect(array_keys($parameters),['app_key','access_token','sign','sign_method','timestamp']))throw new RuntimeException('Lazada authentication fields cannot be overridden.');
        foreach($parameters as $name=>$value){
            $providerEnvelope=($path==='/order/shipment/providers/get'&&$name==='getShipmentProvidersReq')||($path==='/order/package/document/get'&&$name==='getDocumentReq')
                ||($path==='/category/brands/query'&&in_array($name,['startRow','pageSize'],true))
                ||($path==='/seller/warehouse/get'&&$name==='addressTypes');
            if(!is_string($name)||(!$providerEnvelope&&!preg_match('/^[a-z][a-z0-9_]*$/D',$name))||(!is_string($value)&&!is_int($value)))throw new RuntimeException('Invalid Lazada read parameters.');
        }
        for ($attempt=0; $attempt<2; $attempt++) {
            $query=$parameters+['app_key'=>$key,'access_token'=>$accessToken,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
            ksort($query,SORT_STRING);$base=$path;
            foreach($query as $name=>$value)$base.=$name.$value;
            $query['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
            try{
                $response=Http::acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->get('https://api.lazada.com.my/rest'.$path,$query);
            }catch(ConnectionException){throw new RuntimeException('Lazada read request could not be completed.');}
            $payload=$response->json();
            // Lazada explicitly reports that this seller-side limit clears after one second.
            // Reads are idempotent, so one bounded retry is safe and preserves user intent.
            if ($attempt===0 && $response->successful() && is_array($payload)
                && in_array($payload['code']??null,['SellerCallLimit','ServiceTimeout'],true)) {
                usleep(1_100_000);
                continue;
            }
            break;
        }
        if (in_array($path,['/order/shipment/providers/get','/order/package/document/get'],true)) {
            if (!$response->successful()||!is_array($payload)||!in_array($payload['code']??null,['0',0],true)
                ||($payload['result']['success']??null)!==true||!is_array($payload['result']['data']??null)) {
                throw new RuntimeException('Lazada shipment provider response was not confirmed.');
            }
            return $payload['result']['data'];
        }
        if(in_array($path,['/seller/warehouse/get','/rc/warehouse/get','/rc/warehouse/detail/get'],true)) {
            if(!$response->successful()||!is_array($payload)||!in_array($payload['code']??null,['0',0],true)
                ||($payload['result']['success']??null)!==true||!is_array($payload['result']['module']??null)) {
                throw new RuntimeException('Lazada warehouse request failed or returned an incomplete response.');
            }
            return $payload['result']['module'];
        }
        if(!$response->successful()||!is_array($payload)||!in_array($payload['code']??null,['0',0],true)
            ||!is_array($payload['data']??null)) {
            $requestId=is_array($payload)?($payload['request_id']??null):null;
            if (!is_string($requestId) || !preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$requestId)) $requestId=null;
            \Illuminate\Support\Facades\Log::warning('Lazada read request was not confirmed',[
                'path'=>$path,
                'http_status'=>$response->status(),
                'provider_code'=>is_array($payload)?($payload['code']??null):null,
                'request_id'=>$requestId,
            ]);
            throw new RuntimeException('Lazada read request failed or returned an incomplete response at '.$path.'.');
        }
        return $payload['data'];
    }

    /** @return array{key:string,secret:string} */
    private function credentials(?int $storeId): array
    {
        $resolver=app(LazadaAppCredentialResolver::class);
        return $storeId===null ? $resolver->production() : $resolver->forStore($storeId);
    }
    public function isConfigured(): bool
    {
        return config('marketplace.lazada.enabled') === true
            && preg_match('/^[1-9][0-9]*$/D', (string) config('marketplace.lazada.app_key')) === 1
            && is_string(config('marketplace.lazada.app_secret'))
            && trim(config('marketplace.lazada.app_secret')) !== '';
    }

    public function isAuthorizationReady(): bool
    {
        if (!$this->isConfigured()) return false;
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
