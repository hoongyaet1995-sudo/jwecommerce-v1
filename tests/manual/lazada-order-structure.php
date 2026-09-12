<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local')) exit(1);
$row=Illuminate\Support\Facades\DB::table('marketplace_orders as o')->join('marketplace_stores as s','s.id','=','o.store_id')
    ->where('s.platform','lazada')->where('o.external_order_id','512827127743475')->select('o.encrypted_payload')->first();
if (!$row) exit(1);
$payload=json_decode(Illuminate\Support\Facades\Crypt::decryptString($row->encrypted_payload),true,512,JSON_THROW_ON_ERROR);
echo json_encode(['order_fields'=>array_keys($payload),'shipping_fields'=>array_keys($payload['address_shipping']??[]),
    'billing_fields'=>array_keys($payload['address_billing']??[])]).PHP_EOL;
