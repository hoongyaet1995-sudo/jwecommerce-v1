<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (!$app->environment('local') || DB::connection()->getDatabaseName()!=='jw_ecommerce') throw new RuntimeException('Local only.');
$variant=DB::table('product_variants')->where('sku','JW-QA-BASIC-FIRST-COUNT-0908')->first();
if (!$variant || $variant->data_classification!=='test') throw new RuntimeException('Test fixture missing.');
$listing=DB::table('marketplace_listing_variants as l')->leftJoin('marketplace_variant_mappings as m','m.listing_variant_id','=','l.id')
 ->where('l.store_id',3)->where('l.external_product_id','14971011493')
 ->get(['l.id','l.store_id','l.external_product_id','l.external_variant_id','m.product_variant_id','m.actor_id']);
echo json_encode(['listing'=>$listing,'test_variant_id'=>$variant->id,'legacy_stock'=>$variant->stock,
 'balances'=>DB::table('inventory_balances')->where('product_variant_id',$variant->id)->get(['id','physical_stock','safety_stock','version'])],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
