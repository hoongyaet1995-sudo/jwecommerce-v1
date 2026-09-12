<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))throw new RuntimeException('Local acceptance tool only.');
$actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
$store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('id',3)->where('platform','lazada')->where('external_shop_id','300163725140')->first();
$variant=Illuminate\Support\Facades\DB::table('product_variants')->where('id',102)->where('sku','JW-SBX-LZ-SHIRT-001')->first();
if(!$store||!$variant)throw new RuntimeException('Approved fixture identity changed.');
$balance=Illuminate\Support\Facades\DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
 ->where('b.product_variant_id',$variant->id)->where('p.warehouse_id',9)->where('p.kind','warehouse')->value('b.id');
if(!$balance)throw new RuntimeException('Approved test balance missing.');
// These scoped flags expire with this explicitly invoked test process. Production broadcasting remains closed.
config(['marketplace.sandbox_stock_writes_enabled'=>true,'marketplace.lazada.test_store_id'=>$store->id]);
if(config('marketplace.stock_sync_operations_enabled'))throw new RuntimeException('General stock broadcasting must remain closed.');
$service=app(App\Services\Marketplace\SandboxInventoryAttemptService::class);
$key='6866eb81-0f26-4a80-adf7-0dbb3f0bc96a';
try {
 $receipt=$service->stage((int)$store->id,(int)$balance,$key,'BASIC-WMS-20260908 bounded borrowed-seller quantity acceptance',$actor);
 if($receipt['status']==='staged')$receipt=$service->dispatch($key,$actor);
 echo json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
 exit($receipt['status']==='confirmed'?0:2);
}catch(Throwable $error){fwrite(STDERR,'Bounded sandbox test stopped: '.get_class($error).'. Inspect the persisted attempt before retrying.'.PHP_EOL);exit(1);}
