<?php

// One idempotent out-of-stock listing used to prove test-app create and local mapping.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    if (!app()->environment('local')) throw new RuntimeException('Local acceptance only.');
    $storeId=(int)config('marketplace.lazada.test_store_id');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->find($storeId);
    if (!$store || $store->external_shop_id!=='300163725140' || $store->account_mode!=='test') throw new RuntimeException('Designated test store required.');
    $requestKey='9c04ae84-f403-4a7e-bcad-c80b906e625a';
    $existing=Illuminate\Support\Facades\DB::table('marketplace_publishing_tasks')->where('store_id',$storeId)->where('request_key',$requestKey)->first();
    if ($existing) {
        echo json_encode(['task_id'=>$existing->id,'status'=>$existing->status,'external_item_id'=>$existing->external_item_id,'reused'=>true]).PHP_EOL;
        exit($existing->status==='succeeded'?0:1);
    }
    $actor=App\Models\User::where('is_admin',true)->orderBy('id')->firstOrFail();
    $templateTask=Illuminate\Support\Facades\DB::table('marketplace_publishing_tasks')->where('id',4)->first();
    $templateProduct=App\Models\Product::findOrFail($templateTask->product_id);
    $templateVariant=$templateProduct->variants()->where('enabled',true)->firstOrFail();
    [$product,$variant]=Illuminate\Support\Facades\DB::transaction(function () use ($templateProduct,$templateVariant) {
        $product=$templateProduct->replicate();
        $product->title='JW Dual App Acceptance 20260919';
        $product->handle='jw-dual-app-acceptance-20260919';
        $product->status='draft';
        $product->save();
        $variant=$templateVariant->replicate();
        $variant->product_id=$product->id;
        $variant->title='Default';
        $variant->sku='JW-LZ-DUAL-APP-20260919';
        $variant->stock=0;
        $variant->data_classification='test';
        $variant->save();
        return [$product,$variant];
    });
    $payload=json_decode($templateTask->payload,true,512,JSON_THROW_ON_ERROR);
    data_set($payload,'Request.Product.Attributes.name',$product->title);
    data_set($payload,'Request.Product.Attributes.description','<p>JW ECOM dual-app test listing. Out of stock and not for sale.</p>');
    $sku=&$payload['Request']['Product']['Skus']['Sku'][0];
    $sku['SellerSku']=$variant->sku;$sku['quantity']='0';
    foreach ($sku['MultiWarehouseInventories']['MultiWarehouseInventory']??[] as &$warehouse) $warehouse['Quantity']='0';
    unset($warehouse);
    $source=app(App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
    $taskId=app(App\Services\Marketplace\PublishingTasks::class)->create($storeId,$product->id,$actor->id,$requestKey,$payload,$source);
    // The payload already contains a confirmed Lazada-hosted image from the same test seller.
    // This probe targets transport and mapping, so it does not migrate another image.
    config(['marketplace.basic_wms_enabled'=>false]);
    $result=app(App\Services\Marketplace\MarketplacePublishingDispatchService::class)->dispatch($taskId);
    $mapping=Illuminate\Support\Facades\DB::table('marketplace_variant_mappings')->where('store_id',$storeId)->where('product_variant_id',$variant->id)->first();
    echo json_encode($result+['mapping_confirmed'=>(bool)$mapping,'master_sku'=>$variant->sku],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Publish acceptance stopped: '.get_class($error).' '.preg_replace('/[^A-Za-z0-9 ._-]/','',$error->getMessage()).PHP_EOL);
    exit(1);
}
