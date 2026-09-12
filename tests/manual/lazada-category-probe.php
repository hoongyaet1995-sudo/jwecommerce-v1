<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 if (!app()->environment('local')) throw new RuntimeException('Local only');
 $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
 $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
 $attributes=app(App\Services\Marketplace\LazadaClient::class)->read('/category/attributes/get',$token,['primary_category_id'=>'10000664']);
 $result=[];
 foreach ($attributes as $attribute) {
  if (($attribute['is_mandatory']??0)==1 || ($attribute['name']??null)==='brand') $result[]=$attribute;
 }
 echo json_encode(['attribute_count'=>count($attributes),'mandatory'=>$result],JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'Category read failed: '.get_class($error).PHP_EOL); exit(1); }
