<?php
// Real transport diagnostic: missing category and SKU fields cannot create a product.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only.');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store) throw new RuntimeException('Approved test store missing.');
    $token=App\Models\MarketplaceStoreToken::where('store_id',$store->id)->first();
    if ($token?->credential_source!=='official_test') throw new RuntimeException('Official test credential required.');
    $access=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $path='/product/create';
    $parameters=['payload'=>'{"Request":{"Product":{}}}',
        'app_key'=>(string)config('marketplace.lazada.app_key'),'access_token'=>$access,
        'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
    ksort($parameters,SORT_STRING); $base=$path;
    foreach ($parameters as $name=>$value) $base.=$name.$value;
    $parameters['sign']=strtoupper(hash_hmac('sha256',$base,config('marketplace.lazada.app_secret')));
    $response=Illuminate\Support\Facades\Http::asForm()->acceptJson()->connectTimeout(10)->timeout(30)
        ->withoutRedirecting()->post('https://api.lazada.com.my/rest'.$path,$parameters);
    $data=$response->json();
    // Provider text and request URLs may contain credentials; report codes only.
    $message=is_string($data['message']??null)?$data['message']:'';
    $message=str_replace([$access,(string)config('marketplace.lazada.app_secret'),$parameters['sign']],'[redacted]',$message);
    echo json_encode(['http_status'=>$response->status(),'code'=>$data['code']??null,'message'=>substr($message,0,300),
        'response_keys'=>is_array($data)?array_keys($data):[], 'missing_product_fields_probe'=>true]).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Contract probe failed ('.get_class($error).'). No credentials printed.'.PHP_EOL);
    exit(1);
}

