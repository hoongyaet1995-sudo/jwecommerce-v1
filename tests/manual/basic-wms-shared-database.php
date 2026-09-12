<?php
// Local-only empty MySQL fixture. Never copies provider credentials or modifies source stock.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

$target='jw_basic_wms_shared_acceptance_0908';
if (!$app->environment('local') || config('database.default')!=='mysql'
    || !in_array(config('database.connections.mysql.host'),['127.0.0.1','localhost'],true)
    || config('database.connections.mysql.url')) throw new RuntimeException('Local direct MySQL required.');
$source=DB::connection()->getDatabaseName();
if ($source===$target) throw new RuntimeException('Prepare from the original local database only.');
$actor=DB::table('users')->where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->first();
if (!$actor) throw new RuntimeException('Authorized local administrator missing.');
if (DB::select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',[$target]))
    throw new RuntimeException('Acceptance database already exists; inspect it instead of resetting.');
DB::statement('CREATE DATABASE `jw_basic_wms_shared_acceptance_0908` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.connections.mysql.database'=>$target]);
DB::purge('mysql');
if (DB::connection()->getDatabaseName()!==$target) throw new RuntimeException('Target connection mismatch.');
if (Artisan::call('migrate',['--database'=>'mysql','--force'=>true])!==0) throw new RuntimeException('Fixture migration failed.');
DB::table('users')->insert((array)$actor);
$product=App\Models\Product::create(['title'=>'Shared pool browser acceptance','handle'=>'qa-shared-pool-0908','base_price'=>1,'status'=>'draft']);
$variant=$product->variants()->create(['sku'=>'JW-QA-SHARED-0908','title'=>'Local test fixture','price'=>1,'stock'=>17,'enabled'=>true]);
app(App\Services\Inventory\TestDataClassificationService::class)->markTest('sku',$variant->id,
    App\Models\User::findOrFail($actor->id),'Isolated local shared pool browser acceptance');
echo json_encode(['source_database'=>$source,'acceptance_database'=>$target,'variant_id'=>$variant->id,
    'legacy_stock'=>17,'operating_mode'=>DB::table('inventory_configuration')->value('operating_mode'),
    'balances'=>DB::table('inventory_balances')->count(),'stores'=>DB::table('marketplace_stores')->count()],JSON_THROW_ON_ERROR).PHP_EOL;
