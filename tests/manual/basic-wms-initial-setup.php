<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))throw new RuntimeException('Local acceptance only.');
$actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
$snapshot=fn()=>[
    'variants'=>Illuminate\Support\Facades\DB::table('product_variants')->orderBy('id')->get(['id','stock','stock_version'])->toJson(),
    'balances'=>Illuminate\Support\Facades\DB::table('inventory_balances')->orderBy('id')->get()->toJson(),
    'allocations'=>Illuminate\Support\Facades\DB::table('stock_allocations')->orderBy('id')->get()->toJson(),
];
$before=$snapshot();
$receipt=app(App\Services\Inventory\BasicWmsConfigurationService::class)->configure(
    'independent_warehouses',3,'Local Basic WMS acceptance: existing warehouse balances; no order takeover',
    '08548a2e-1ddb-44f2-904d-084280eb6bb6',$actor);
if($before!==$snapshot())throw new RuntimeException('Initial configuration changed inventory data.');
echo json_encode(['receipt'=>$receipt,'inventory_unchanged'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
