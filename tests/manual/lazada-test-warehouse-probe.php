<?php
// Approved Loan Test Account only. No mutations or credential output.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 if((string)config('marketplace.lazada.app_key')!=='139209')throw new RuntimeException('Unexpected app');
 $token=trim(fgets(STDIN));$client=app(App\Services\Marketplace\LazadaClient::class);
 $seller=$client->read('/seller/get',$token);
 if((string)($seller['seller_id']??'')!=='300163725140')throw new RuntimeException('Unexpected seller');
 foreach(['/rc/warehouse/get','/rc/warehouse/detail/get'] as $path){
  try {
   $data=$client->read($path,$token);
   $scan=function($value)use(&$scan){
    if(!is_array($value))return null;
    $out=[];
    foreach($value as $key=>$item){
     if(is_array($item))$out[$key]=$scan($item);
     elseif(preg_match('/warehouse|code|type|status|default/i',(string)$key))$out[$key]=$item;
    }
    return $out;
   };
   echo json_encode(['endpoint'=>$path,'keys'=>array_keys($data),'warehouse_fields'=>$scan($data)],JSON_PRETTY_PRINT).PHP_EOL;
  }catch(Throwable $e){echo json_encode(['endpoint'=>$path,'result'=>'unconfirmed','error_class'=>get_class($e)]).PHP_EOL;}
 }
}catch(Throwable $e){fwrite(STDERR,'Probe failed: '.get_class($e).PHP_EOL);exit(1);}
