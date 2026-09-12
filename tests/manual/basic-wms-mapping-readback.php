<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))throw new RuntimeException('Local acceptance only.');
$row=Illuminate\Support\Facades\DB::table('marketplace_variant_mappings as m')
 ->join('marketplace_listing_variants as l','l.id','=','m.listing_variant_id')
 ->join('product_variants as v','v.id','=','m.product_variant_id')
 ->where('v.sku','JW-SBX-LZ-SHIRT-001')->where('l.store_id',3)
 ->select('l.id as listing_id','l.store_id','l.external_product_id','l.external_variant_id','v.id as variant_id','v.sku','m.actor_id')->first();
if(!$row||$row->external_product_id!=='14971376157'||(int)$row->variant_id!==102)throw new RuntimeException('Expected test mapping missing.');
echo json_encode($row,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
