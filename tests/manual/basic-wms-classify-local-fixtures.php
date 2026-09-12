<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))throw new RuntimeException('Local fixtures only.');
$actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
$result=Illuminate\Support\Facades\DB::transaction(function()use($actor){
    $fixtures=[['product_variants',102,'sku','JW-SBX-LZ-SHIRT-001'],['inventory_warehouses',9,'code','QA-ZIP36-CYB'],['marketplace_stores',3,'external_shop_id','300163725140']];
    $result=[];
    foreach($fixtures as [$table,$id,$identity,$expected]){
        $query=Illuminate\Support\Facades\DB::table($table)->where('id',$id);
        $row=(clone $query)->lockForUpdate()->first();
        if(!$row||$row->$identity!==$expected||!in_array($row->data_classification,['unclassified','test'],true))
            throw new RuntimeException('Fixture identity or classification changed.');
        if($table==='marketplace_stores'&&($row->platform!=='lazada'||!Illuminate\Support\Facades\DB::table('marketplace_store_tokens')->where('store_id',$id)->where('application_id','139209')->where('credential_source','official_test')->exists()))throw new RuntimeException('Official test store provenance missing.');
        app(App\Services\Inventory\TestDataClassificationService::class)->markTest(match($table){'product_variants'=>'sku','inventory_warehouses'=>'warehouse','marketplace_stores'=>'store'},$id,$actor,
          'Explicit local ZIP36 WMS acceptance fixture; quantities are test counts, not operating shelf inventory.');
        $result[]=['table'=>$table,'id'=>$id,'classification'=>'test'];
    }
    return $result;
});
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
