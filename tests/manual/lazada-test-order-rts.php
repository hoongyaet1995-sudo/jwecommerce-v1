<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    $token=App\Models\MarketplaceStoreToken::where('store_id',$store->id)->firstOrFail();
    if ($token->credential_source!=='official_test') throw new RuntimeException('Official test credentials required');
    $order=Illuminate\Support\Facades\DB::table('marketplace_orders')->where('store_id',$store->id)->where('external_order_id','512827127743475')->first();
    $actor=Illuminate\Support\Facades\DB::table('users')->where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->first();
    if (!$order||!$actor) throw new RuntimeException('Test context missing');
    $id=app(App\Services\Marketplace\MarketplaceRtsAttempts::class)->stage($order->id,$actor->id,'ea23a4b9-8a88-43a7-a82b-b8f091d79a08',['FP021111611256684']);
    $status=Illuminate\Support\Facades\DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status');
    if ($status!=='queued') { echo json_encode(['attempt_id'=>$id,'status'=>$status,'resent'=>false]).PHP_EOL; exit; }
    config(['marketplace.fulfillment_operations_enabled'=>true]);
    $result=app(App\Services\Marketplace\LazadaRtsDispatch::class)->dispatch($id);
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Test RTS stopped: '.get_class($error).PHP_EOL);
    if (isset($id)) echo json_encode(['attempt_id'=>$id,'status'=>Illuminate\Support\Facades\DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('status')]).PHP_EOL;
    exit(1);
}
