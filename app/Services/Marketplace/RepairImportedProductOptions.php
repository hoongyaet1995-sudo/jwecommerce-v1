<?php
namespace App\Services\Marketplace;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
final class RepairImportedProductOptions
{
    public function repair(int $storeId,string $itemId,int $productId): array
    {
        app(ShopeeCatalogService::class)->pullItem($storeId,$itemId);
        return DB::transaction(function()use($storeId,$itemId,$productId){
            $product=Product::whereKey($productId)->lockForUpdate()->firstOrFail();
            $variants=$product->variants()->lockForUpdate()->get();
            $rows=DB::table('marketplace_variant_mappings as m')->join('marketplace_listing_variants as l','l.id','=','m.listing_variant_id')
                ->where('m.store_id',$storeId)->where('l.store_id',$storeId)->where('l.external_product_id',$itemId)
                ->whereIn('m.product_variant_id',$variants->pluck('id'))->get(['m.product_variant_id','l.observed_data']);
            if($rows->count()!==$variants->count()||$rows->pluck('product_variant_id')->unique()->count()!==$variants->count()) throw new \RuntimeException('Every variant must have exactly one source mapping.');
            foreach($rows as $row){
                $variant=$variants->firstWhere('id',$row->product_variant_id);
                $data=json_decode($row->observed_data,true);
                $options=$data['variantOptions']??[];
                if(!$options||$data['sku']!==$variant->sku)throw new \RuntimeException('Verified source options and matching SKU required.');
                if($variant->options && $variant->options!=$options)throw new \RuntimeException('Existing variant options differ; review before changing.');
                $variant->update(['options'=>$options]);
                ImportedProductOptions::merge($product,$options);
            }
            $source=app(MarketplacePublishingSource::class)->read($productId);
            $payload=ShopeeMasterVariants::fromSource($source);
            return ['product'=>$productId,'groups'=>$source['options'],'variants'=>count($payload['model']),'mappingCount'=>$rows->count()];
        });
    }
}
