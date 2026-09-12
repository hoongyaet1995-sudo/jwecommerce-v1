<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 if (!app()->environment('local')) exit(1);
 $row=Illuminate\Support\Facades\DB::table('marketplace_orders as o')->join('marketplace_stores as s','s.id','=','o.store_id')
  ->where('s.platform','lazada')->where('s.external_shop_id','300163725140')->where('o.external_order_id','512827127743475')->select('o.id')->first();
 if (!$row) exit(1);
 $id=app(App\Services\Marketplace\LazadaOrderService::class)->refreshOrder($row->id);
 echo json_encode(['order_id'=>$id,'status'=>Illuminate\Support\Facades\DB::table('marketplace_orders')->where('id',$id)->value('platform_status')]).PHP_EOL;
} catch(Throwable $error) {fwrite(STDERR,'Order refresh failed: '.get_class($error).PHP_EOL);exit(1);}
