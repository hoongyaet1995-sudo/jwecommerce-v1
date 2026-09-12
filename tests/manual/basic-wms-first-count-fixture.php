<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))throw new RuntimeException('Local test fixture only.');
$actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
$variant=Illuminate\Support\Facades\DB::transaction(function()use($actor){
 $existing=App\Models\ProductVariant::where('sku','JW-QA-BASIC-FIRST-COUNT-0908')->first();
 if($existing){
  if($existing->data_classification!=='test')throw new RuntimeException('Fixture classification mismatch.');
  return $existing;
 }
 $product=App\Models\Product::create(['title'=>'Basic WMS First Count Test','handle'=>'qa-basic-first-count-0908','base_price'=>1,'status'=>'draft']);
 $variant=$product->variants()->create(['sku'=>'JW-QA-BASIC-FIRST-COUNT-0908','title'=>'Local acceptance fixture','price'=>1,'stock'=>17,'enabled'=>true]);
 Illuminate\Support\Facades\DB::table('product_variants')->where('id',$variant->id)->update([
  'data_classification'=>'test','classified_by'=>$actor->id,'classified_at'=>now(),'classification_reason'=>'Explicit local first-count browser acceptance fixture. Not operating stock.',
 ]);
 return $variant;
});
echo json_encode(['variant_id'=>$variant->id,'sku'=>$variant->sku,'legacy_stock'=>$variant->stock,
 'balances'=>Illuminate\Support\Facades\DB::table('inventory_balances')->where('product_variant_id',$variant->id)->get(['id','physical_stock','safety_stock','version'])],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
