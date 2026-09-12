<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (!$app->environment('local') || DB::connection()->getDatabaseName()!=='jw_ecommerce') throw new RuntimeException('Original local database required.');
$warehouse=DB::table('inventory_warehouses')->where('code','QA-BASIC-PEN-0908')->first();
if (!$warehouse || $warehouse->name!=='LOCAL TEST Basic Penang') throw new RuntimeException('Exact browser fixture missing.');
$actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
app(App\Services\Inventory\TestDataClassificationService::class)->markTest('warehouse',$warehouse->id,$actor,'Explicit local browser warehouse and routing acceptance fixture');
$route=DB::table('inventory_fulfillment_routes')->where('channel_key','website')->first();
if (!$route || (int)$route->warehouse_id!==(int)$warehouse->id) throw new RuntimeException('Browser route did not persist.');
echo json_encode(['warehouse'=>DB::table('inventory_warehouses')->where('id',$warehouse->id)->first(['id','code','name','address','state','status','version','data_classification']),
 'route'=> $route, 'accounting_enabled'=>(bool)DB::table('inventory_configuration')->value('accounting_enabled'),
 'platform_broadcast_enabled'=>(bool)config('marketplace.stock_sync_operations_enabled')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
