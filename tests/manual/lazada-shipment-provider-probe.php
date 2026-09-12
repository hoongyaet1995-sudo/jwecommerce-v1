<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\ResponseReceived::class,function($event) use (&$token) {
 $body=$event->response->json();
 echo json_encode(['http'=>$event->response->status(),'fields'=>is_array($body)?array_keys($body):[],
  'result_fields'=>is_array($body['result']??null)?array_keys($body['result']):[],
  'data_fields'=>is_array($body['data']??null)?array_keys($body['data']):[],
  'code'=>preg_match('/^[A-Za-z0-9_.-]{1,40}$/',(string)($body['code']??''))?$body['code']:null,
  'safe_message'=>($body['code']??null)==='MissingParameter'?substr(str_replace(array_filter([$token??null,config('marketplace.lazada.app_secret')]),'[redacted]',$body['message']??''),0,200):null,'missing_parameter'=>($body['code']??null)==='MissingParameter'&&preg_match('/["\x27]([A-Za-z][A-Za-z0-9_]{0,60})["\x27]/',$body['message']??'',$match)?$match[1]:null]).PHP_EOL;
});
try {
 if (!app()->environment('local')) exit(1);
 $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
 $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
 $data=app(App\Services\Marketplace\LazadaClient::class)->read('/order/shipment/providers/get',$token,
  ['getShipmentProvidersReq'=>json_encode(['orders'=>[['order_id'=>'512827127743475','order_item_ids'=>['512827127843475','512827127943475']]],'shipping_allocate_type'=>'TFS'])]);
 echo json_encode(['fields'=>array_keys($data),'platform_default'=>$data['platform_default']??null,'shipping_allocate_type'=>$data['shipping_allocate_type']??null]).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'Provider query failed: '.get_class($error).PHP_EOL); exit(1); }



