<?php
namespace App\Services\Marketplace;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
final class LocalizeProductMedia
{
 public function run(int $productId):array {
  $product=Product::with(['images','variants'])->findOrFail($productId);
  $paths=$product->images->pluck('path')->merge($product->variants->pluck('image_url'))->merge($product->variants->pluck('option_image'))->filter()->unique();
  $replacements=[];$ingest=app(MarketplaceMediaIngest::class);
  foreach($paths as $path)if($ingest->supports($path))$replacements[$path]=$ingest->ingest($path);
  return DB::transaction(function()use($productId,$replacements){
   $product=Product::whereKey($productId)->lockForUpdate()->firstOrFail();
   foreach($product->images()->lockForUpdate()->get() as $image)if(isset($replacements[$image->path]))$image->update(['path'=>$replacements[$image->path]]);
   foreach($product->variants()->lockForUpdate()->get() as $variant){
    $changes=[];foreach(['image_url','option_image'] as $field)if(isset($replacements[$variant->$field]))$changes[$field]=$replacements[$variant->$field];
    if($changes)$variant->update($changes);
   }
   $options=$product->options??[];
   foreach($options as $i=>$option)foreach(($option['valueVisuals']??[]) as $value=>$visual)if(isset($replacements[$visual['optionImage']??'']))$options[$i]['valueVisuals'][$value]['optionImage']=$replacements[$visual['optionImage']];
   $product->update(['options'=>$options]);
   app(\App\Domain\Catalog\CatalogMediaReferenceSynchronizer::class)->syncProduct($product);
   return ['productId'=>$productId,'sourceImages'=>count($replacements),'managedImages'=>count(array_unique($replacements))];
  });
 }
}
