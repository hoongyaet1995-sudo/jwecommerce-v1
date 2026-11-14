<?php
/** Read-only CMS release receipt. Emits hashes and counts, never setting values. */
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$out=[];
foreach(['pages','page_documents','menus','menu_items','store_settings'] as $table){
 $rows=Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->map(function($row){$value=(array)$row;unset($value['created_at'],$value['updated_at']);ksort($value);return $value;})->all();
 $out[$table]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
}
echo json_encode(['base'=>base_path(),'public'=>public_path(),'cms'=>$out],JSON_THROW_ON_ERROR).PHP_EOL;
