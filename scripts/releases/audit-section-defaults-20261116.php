<?php
/** Read-only release receipt. Never emits page contents, setting values or credentials. */
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables=[];
foreach(['pages','page_documents','menus','menu_items','store_settings','cms_media_assets','page_media_references'] as $table){
 if(!Illuminate\Support\Facades\Schema::hasTable($table))continue;
 $rows=Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->map(function($row){$value=(array)$row;unset($value['created_at'],$value['updated_at']);ksort($value);return $value;})->all();
 $tables[$table]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
}
echo json_encode(['base'=>base_path(),'public'=>public_path(),'host'=>parse_url(config('app.url'),PHP_URL_HOST),'envHash'=>is_file(base_path('.env'))?hash_file('sha256',base_path('.env')):null,'tables'=>$tables],JSON_THROW_ON_ERROR).PHP_EOL;
