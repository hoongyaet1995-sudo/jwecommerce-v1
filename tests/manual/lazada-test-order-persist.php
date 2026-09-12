<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
 if(!$store||!app()->environment('local'))throw new RuntimeException('Local test store required');
 $from=Carbon\CarbonImmutable::parse('2026-09-08 14:30:00','Asia/Kuala_Lumpur')->timestamp;
 $to=Carbon\CarbonImmutable::parse('2026-09-08 14:35:00','Asia/Kuala_Lumpur')->timestamp;
 $service=app(App\Services\Marketplace\LazadaOrderService::class);
 $first=$service->pullPage($store->id,$from,$to);
 $before=Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$store->id)->count();
 $second=$service->pullPage($store->id,$from,$to);
 $after=Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$store->id)->count();
 echo json_encode(['first'=>$first,'second'=>$second,'rows_before_replay'=>$before,'rows_after_replay'=>$after]).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Order capture failed: '.get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
