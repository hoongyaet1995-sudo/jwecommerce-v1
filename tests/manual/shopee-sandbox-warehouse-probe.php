<?php

// Explicit test-shop read only. Execute from the application root; never print credentials or addresses.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

try {
    if (config('marketplace.shopee.environment') !== 'sandbox' || !config('marketplace.read_operations_enabled')) {
        throw new RuntimeException('Sandbox read gate required');
    }
    $store=DB::table('marketplace_stores')->where('id',1)->first();
    if (!$store || $store->platform !== 'shopee' || $store->environment !== 'sandbox'
        || $store->status !== 'connected' || (string)$store->external_shop_id !== '227482706') {
        throw new RuntimeException('Approved test shop required');
    }
    $token=app(\App\Services\Marketplace\ShopeeTokenManager::class)->accessToken(1);
    $config=config('marketplace.shopee');
    $path='/api/v2/shop/get_warehouse_detail';
    $time=now()->timestamp;
    $response=Http::acceptJson()->connectTimeout(10)->timeout(20)->withoutRedirecting()->get(
        'https://openplatform.sandbox.test-stable.shopee.sg'.$path,[
            'partner_id'=>(int)$config['partner_id'],'timestamp'=>$time,'shop_id'=>$store->external_shop_id,
            'access_token'=>$token,'warehouse_type'=>1,
            'sign'=>hash_hmac('sha256',$config['partner_id'].$path.$time.$token.$store->external_shop_id,$config['partner_key']),
        ]);
    $body=$response->json();
    $error=$body['error'] ?? null;
    $safeError=is_string($error) && preg_match('/\A[a-zA-Z0-9_.]{0,100}\z/D',$error) ? $error : 'unclassified';
    $rows=$body['response'] ?? null;
    echo json_encode(['http_status'=>$response->status(),'error_code'=>$safeError,
        'response_is_list'=>is_array($rows) && array_is_list($rows),
        'warehouse_count'=>is_array($rows) && array_is_list($rows) ? count($rows) : null],JSON_PRETTY_PRINT)."\n";
} catch (Throwable $error) {
    fwrite(STDERR,'Probe failed: '.get_class($error)."\n");
    exit(1);
}
