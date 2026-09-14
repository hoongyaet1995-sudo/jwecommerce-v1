<?php

namespace App\Services\Marketplace;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/** Catalog review only; provider attributes and write permission require separate validation. */
final class MarketplacePublishingSource
{
    public function read(int $productId): array
    {
        return DB::transaction(function () use ($productId) {
            $product=Product::whereKey($productId)->lockForUpdate()->firstOrFail();
            $variants=$product->variants()->where('enabled',true)->orderBy('id')->lockForUpdate()->get();
            abort_unless(trim($product->title)!=='' && $variants->isNotEmpty(),422,'Review the product title and enabled variants.');
            $source=['product_id'=>$product->id,'title'=>$product->title,'description'=>$product->description,
                'brand'=>$product->brand,'options'=>$product->options,'variants'=>[]];
            foreach ($variants as $variant) {
                abort_unless(trim($variant->sku)!=='' && is_numeric($variant->price) && (int)$variant->price>0,
                    422,'Each enabled variant requires a SKU and positive price.');
                $source['variants'][]=['id'=>$variant->id,'sku'=>$variant->sku,'title'=>$variant->title,
                    'price_minor'=>(int)$variant->price,'options'=>$variant->options,
                    'image_url'=>$variant->image_url,'option_image'=>$variant->option_image,
                    'weight_grams'=>$variant->weight_grams,'package_length_cm'=>$variant->package_length_cm,
                    'package_width_cm'=>$variant->package_width_cm,'package_height_cm'=>$variant->package_height_cm,
                    'classification'=>$variant->data_classification,'initial_quantity'=>0];
            }
            $source['images']=$product->images()->orderBy('id')->lockForUpdate()->get(['id','path'])->toArray();
            $source['revision']=hash('sha256',json_encode($source,JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
            return $source;
        });
    }
}
