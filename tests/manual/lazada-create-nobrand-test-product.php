<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store || App\Models\MarketplaceStoreToken::where('store_id',$store->id)->value('credential_source')!=='official_test') throw new RuntimeException('Test credential required');
    $actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
    $key='3c017dcc-8bfb-46ae-8279-9f75aaf65e81';
    $existing=Illuminate\Support\Facades\DB::table('marketplace_publishing_tasks')->where('store_id',$store->id)->where('request_key',$key)->first();
    if ($existing) {
        echo json_encode(['existing_task'=>$existing->id,'status'=>$existing->status,'item_id'=>$existing->external_item_id]).PHP_EOL;
        exit(0);
    }
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $source=app(App\Services\Marketplace\LazadaClient::class)->read('/product/item/get',$token,['item_id'=>'14984118945']);
    $sourceSku=collect($source['skus']??[])->firstWhere('SkuId',128571056069);
    if (!$sourceSku || (int)($source['primary_category']??0)!==10000664) throw new RuntimeException('Source mismatch');
    $sku=array_intersect_key($sourceSku,array_flip(['package_height','package_length','package_width','package_weight','package_content']));
    $sku+=['SellerSku'=>'JW-SBX-LZ-NOBRAND-20260908','quantity'=>'0','price'=>'50.00','Status'=>'inactive'];
    $attributes=array_intersect_key($source['attributes'],array_flip(['warranty_type','Hazmat']));
    $attributes+=['brand'=>'No Brand','name'=>'JW Sandbox NoBrand Mouse 20260908','description'=>'<p>JW integration test product. Not for sale.</p>'];
    $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10000664','Images'=>['Image'=>$source['images']],
        'Attributes'=>$attributes,'Skus'=>['Sku'=>[$sku]]]]];
    $task=Illuminate\Support\Facades\DB::transaction(function () use ($store,$actor,$key,$payload) {
        $source=App\Models\Product::findOrFail(53);
        $product=$source->replicate();
        $product->title='JW Sandbox NoBrand Mouse 20260908'; $product->handle='jw-sandbox-nobrand-mouse-20260908';
        $product->status='draft'; $product->save();
        $variant=App\Models\ProductVariant::findOrFail(94)->replicate();
        $variant->product_id=$product->id; $variant->sku='JW-SBX-LZ-NOBRAND-20260908'; $variant->stock=0; $variant->save();
        return app(App\Services\Marketplace\PublishingTasks::class)->create($store->id,$product->id,$actor->id,$key,$payload);
    });
    echo json_encode(['task_id'=>$task,'submitted_sku'=>$sku['SellerSku']]).PHP_EOL;
    config(['marketplace.publish_operations_enabled'=>true]);
    echo json_encode(app(App\Services\Marketplace\MarketplacePublishingDispatchService::class)->dispatch($task)).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'Create stopped: '.get_class($error).'. Inspect task before retry.'.PHP_EOL); exit(1); }

