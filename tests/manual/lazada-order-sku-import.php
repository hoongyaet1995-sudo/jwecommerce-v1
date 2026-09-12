<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!app()->environment('local')) exit(1);
    $actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    if (!$store) exit(1);
    $listing=Illuminate\Support\Facades\DB::table('marketplace_listing_variants')->where('store_id',$store->id)
        ->where('external_product_id','14971376157')->where('external_variant_id','128558080469')->first();
    if (!$listing) {
        $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
        $item=app(App\Services\Marketplace\LazadaClient::class)->read('/product/item/get',$token,['item_id'=>'14971376157']);
        if ((string)($item['item_id']??'')!=='14971376157') throw new RuntimeException('Product identity mismatch');
        $matches=array_values(array_filter($item['skus']??[],fn($sku)=>(string)($sku['SkuId']??'')==='128558080469'));
        if (count($matches)!==1) throw new RuntimeException('SKU identity mismatch');
        $sku=$matches[0];
        $observed=['productImages'=>$item['images']??[],'brand'=>data_get($item,'attributes.brand'),
            'sourceDescriptionHtml'=>data_get($item,'attributes.description'),'productTitle'=>data_get($item,'attributes.name'),
            'title'=>$sku['SellerSku']??null,'sku'=>$sku['SellerSku']??null,'price'=>$sku['price']??null,'currency'=>'MYR',
            'stock'=>$sku['quantity']??null,'stockInfo'=>array_intersect_key($sku,array_flip(['multiWarehouseInventories','fblWarehouseInventories','channelInventories'])),
            'status'=>$sku['Status']??null,'externalCategoryId'=>$item['primary_category']??null];
        Illuminate\Support\Facades\DB::table('marketplace_listing_variants')->insertOrIgnore(['store_id'=>$store->id,
            'external_product_id'=>'14971376157','external_variant_id'=>'128558080469','observed_data'=>json_encode($observed,JSON_THROW_ON_ERROR),
            'fetched_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $listing=Illuminate\Support\Facades\DB::table('marketplace_listing_variants')->where('store_id',$store->id)
            ->where('external_product_id','14971376157')->where('external_variant_id','128558080469')->first();
    }
    $service=app(App\Services\Marketplace\MarketplaceImportService::class);
    $id=$service->importVariantAsProduct($listing->id,$actor->id,'JW-SBX-LZ-SHIRT-001');
    $again=$service->importVariantAsProduct($listing->id,$actor->id,'JW-SBX-LZ-SHIRT-001');
    $variant=App\Models\ProductVariant::findOrFail($id);
    echo json_encode(['import_id'=>$id,'replay_same_id'=>$again===$id,'product_id'=>$variant->product_id,
        'product_status'=>App\Models\Product::findOrFail($variant->product_id)->status,'jw_sku'=>$variant->sku,'available_stock'=>$variant->stock,
        'mapping'=>Illuminate\Support\Facades\DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listing->id)->get(['product_variant_id'])]).PHP_EOL;
} catch(Throwable $error) {fwrite(STDERR,'Order SKU import failed: '.get_class($error).PHP_EOL);exit(1);}
