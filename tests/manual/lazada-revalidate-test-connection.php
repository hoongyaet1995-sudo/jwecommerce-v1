<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    $token=App\Models\MarketplaceStoreToken::where('store_id',$store->id)->firstOrFail();
    if ($token->credential_source!=='official_test') throw new RuntimeException('Test credentials required');
    echo json_encode(['lease_expired'=>!$token->test_lease_expires_at?->isFuture()]).PHP_EOL;
    $id=app(App\Services\Marketplace\LazadaTestConnectionService::class)->connect($token->access_token);
    echo json_encode(['revalidated_store_id'=>$id]).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'Test revalidation failed: '.get_class($error).PHP_EOL); exit(1); }
