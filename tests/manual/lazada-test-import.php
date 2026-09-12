<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 if(!app()->environment('local'))throw new RuntimeException('Local test only');
 $actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
 $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
 $listing=Illuminate\Support\Facades\DB::table('marketplace_listing_variants')->where('store_id',$store->id)->where('external_product_id','14984118945')->first();
 if(!$listing)throw new RuntimeException('Reviewed test listing missing');
 $service=app(App\Services\Marketplace\MarketplaceImportService::class);
 $id=$service->importProduct($store->id,$listing->external_product_id,$actor->id,[(string)$listing->id=>'JW-SBX-LZ-MOUSE-001']);
 $again=$service->importProduct($store->id,$listing->external_product_id,$actor->id,[(string)$listing->id=>'JW-SBX-LZ-MOUSE-001']);
 $p=App\Models\Product::findOrFail($id);
 echo json_encode(['product_id'=>$id,'replay_same_id'=>$again===$id,'status'=>$p->status,'images'=>$p->images()->count(),'description_present'=>strlen($p->description??'')>0,'variants'=>$p->variants()->get(['id','sku','price','stock'])->toArray()]).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Import failed: '.get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
