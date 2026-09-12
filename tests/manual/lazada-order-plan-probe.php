<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local')) exit(1);
$store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
$row=Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$store->id)->where('external_order_id','512827127743475')->first();
if (!$row) exit(2);
$payload=json_decode(Illuminate\Support\Facades\Crypt::decryptString($row->encrypted_payload),true,512,JSON_THROW_ON_ERROR);
// Parser evidence only. Actual activation cutoff is enforced by the future executor.
$plan=App\Services\Marketplace\LazadaInventoryPlan::build($payload,1);
echo json_encode(['order_id'=>$row->id,'plan'=>$plan,'inventory_mutations'=>0]).PHP_EOL;
