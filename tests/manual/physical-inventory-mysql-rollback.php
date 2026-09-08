<?php
// Local real-engine integration probe. All fixture writes roll back.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\InventoryWarehouseService;
use App\Services\Inventory\PhysicalCountReviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (!$app->environment('local') || DB::connection()->getDriverName()!=='mysql') {
    throw new RuntimeException('This probe requires the local MySQL development environment.');
}
$actor=User::query()->where('is_admin',true)->firstOrFail();
$tables=['inventory_warehouses','inventory_pools','inventory_balances','inventory_count_reviews','inventory_physical_movements','inventory_warehouse_changes','inventory_movements','products','product_variants'];
$before=[];
foreach($tables as $table)$before[$table]=DB::table($table)->count();
$configBefore=(array)DB::table('inventory_configuration')->where('id',1)->first();
$assert=function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
DB::beginTransaction();
try {
    $tag='MYSQL-'.bin2hex(random_bytes(6));
    $warehouseService=app(InventoryWarehouseService::class);
    $warehouse=$warehouseService->save(null,null,['code'=>$tag,'name'=>'Rollback-only integration fixture','status'=>'active','is_default_master'=>false],$actor);
    $variant=Product::create(['title'=>$tag,'handle'=>strtolower($tag),'base_price'=>100])
        ->variants()->create(['sku'=>$tag,'title'=>'Rollback-only fixture','price'=>100,'stock'=>97]);
    $provisioner=app(InventoryBalanceProvisioner::class);
    $balance=$provisioner->provision($variant->id,'warehouse',$warehouse['id']);
    $assert($balance['physical_stock']===null && $balance['atp']===null,'Unknown baseline was invented.');
    $assert($balance===$provisioner->provision($variant->id,'warehouse',$warehouse['id']),'Provision retry changed baseline.');
    $service=app(PhysicalCountReviewService::class);
    $review=$service->review($balance['balance_id'],10,'Rollback-only physical count',$actor);
    $receipt=$service->confirm($review['id'],$actor,true);
    $assert($receipt['physical_after']===10 && $receipt['physical_before']===null,'Physical receipt mismatch.');
    $assert($receipt===$service->confirm($review['id'],$actor,true),'Confirmation retry changed receipt.');
    $stale=$service->review($balance['balance_id'],11,'Rollback-only stale review',$actor);
    DB::table('product_variants')->where('id',$variant->id)->increment('stock_version');
    $rejected=false;
    try{$service->confirm($stale['id'],$actor,true);}catch(ValidationException){$rejected=true;}
    $assert($rejected,'Stale review was accepted.');
    $assert((int)$variant->fresh()->stock===97,'Legacy available inventory changed.');
    $assert(DB::table('inventory_balances')->where('id',$balance['balance_id'])->value('obligations_reconciled_at')===null,'Count incorrectly established order coverage.');
} finally {
    DB::rollBack();
}
foreach($tables as $table)$assert(DB::table($table)->count()===$before[$table],'Rollback row count mismatch: '.$table);
$assert((array)DB::table('inventory_configuration')->where('id',1)->first()===$configBefore,'Configuration did not roll back.');
echo "PASS: local MySQL warehouse, provision retry, count review/confirmation, retry, stale rejection, legacy stock preservation; fixture rows rolled back.\n";
