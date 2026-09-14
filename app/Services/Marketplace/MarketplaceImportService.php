<?php

namespace App\Services\Marketplace;

use App\Domain\Cms\MediaUrlPolicy;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceImportService
{
    private function variantDetails(array $data): array
    {
        $result=[];
        if (!empty($data['variantOptions'])) {
            $options=$data['variantOptions'];
            abort_unless(is_array($options)&&!array_is_list($options),422,'Invalid channel variant options.');
            foreach($options as $name=>$value) abort_unless(is_string($name)&&trim($name)!==''&&is_string($value)&&trim($value)!=='',422,'Invalid channel variant options.');
            $result['options']=$options;
        }
        $image=$data['variantImage'] ?? null;
        if ($image !== null && $image !== '') {
            abort_unless(is_string($image) && strlen($image)<=255 && MediaUrlPolicy::isSafe($image),422,'Review the variant image URL before import.');
            $result += ['image_url'=>$image,'option_image'=>$image,'visual_type'=>'image'];
        }
        foreach (['weightGrams'=>'weight_grams','packageLengthCm'=>'package_length_cm','packageWidthCm'=>'package_width_cm','packageHeightCm'=>'package_height_cm'] as $source=>$target) {
            $value=$data[$source] ?? null;
            if ($value===null || $value==='') continue;
            abort_unless(is_numeric($value) && is_finite((float)$value) && (float)$value>=0 && (float)$value<=1000000,422,'Review the channel package measurements before import.');
            if ((float)$value>0) $result[$target]=$value;
        }
        return $result;
    }

    public function importVariantAsProduct(int $listingId, int $actorId, ?string $reviewedSku = null): int
    {
        $source = DB::table('marketplace_listing_variants')->find($listingId);
        abort_unless($source, 404);
        return DB::transaction(function () use ($listingId, $actorId, $source, $reviewedSku) {
            abort_unless(DB::table('marketplace_stores')->where('id', $source->store_id)->lockForUpdate()->first(), 404);
            $source = DB::table('marketplace_listing_variants')->where('id', $listingId)
                ->where('store_id', $source->store_id)->lockForUpdate()->first();
            abort_unless($source, 404);
            $identity = DB::table('marketplace_variant_imports')->where('listing_variant_id', $listingId)->lockForUpdate()->first();
            // A retry must never create a second parent or restore a removed mapping.
            if ($identity) return (int) $identity->product_variant_id;
            abort_if(DB::table('marketplace_product_imports')->where('store_id', $source->store_id)
                ->where('external_product_id', $source->external_product_id)->exists(),
                409, 'This product was already imported. Select its existing JW product.');
            $data = json_decode($source->observed_data, true, 512, JSON_THROW_ON_ERROR);
            $data=app(MarketplaceMediaIngest::class)->normalize($data);
            $title = $data['productTitle'] ?? null;
            $brand = $data['brand'] ?? null;
            abort_unless(is_string($title) && trim($title) !== '' && mb_strlen($title) <= 255,
                422, 'Review the channel product title before import.');
            abort_unless($brand === null || (is_string($brand) && mb_strlen($brand) <= 255),
                422, 'Review the channel brand before import.');
            $images = $data['productImages'] ?? [];
            abort_unless(is_array($images) && array_is_list($images), 422, 'Review the channel image list before import.');
            if (!$images && !empty($data['productImage'])) $images = [$data['productImage']];
            foreach ($images as $image) {
                abort_unless(is_string($image) && $image !== '' && strlen($image) <= 255 && MediaUrlPolicy::isSafe($image),
                    422, 'Review the channel image URL before import.');
            }
            $product = Product::create(['title'=>trim($title), 'handle'=>'marketplace-'.Str::uuid(),
                'base_price'=>0, 'status'=>'draft', 'has_variants'=>false, 'is_featured'=>false,
                'description'=>$this->descriptions->render($data), 'brand'=>$brand === null ? null : (trim($brand) ?: null)]);
            $variantId = $this->importVariant($listingId, $product->id, $actorId, $reviewedSku);
            $product->update(['base_price'=>ProductVariant::findOrFail($variantId)->price]);
            foreach (array_values(array_unique($images)) as $position => $image) {
                $product->images()->create(['path'=>$image, 'alt'=>trim($title), 'position'=>$position]);
            }
            app(\App\Domain\Catalog\CatalogMediaReferenceSynchronizer::class)->syncProduct($product);
            return $variantId;
        });
    }

    public function importVariant(int $listingId, int $destinationProductId, int $actorId, ?string $reviewedSku = null): int
    {
        $source = DB::table('marketplace_listing_variants')->find($listingId);
        abort_unless($source, 404);
        return DB::transaction(function () use ($listingId, $destinationProductId, $actorId, $source, $reviewedSku) {
            // Match the mapping service's store-first lock order.
            abort_unless(DB::table('marketplace_stores')->where('id', $source->store_id)->lockForUpdate()->first(), 404);
            $source = DB::table('marketplace_listing_variants')->where('id', $listingId)->where('store_id', $source->store_id)->lockForUpdate()->first();
            abort_unless($source, 404);
            $identity = DB::table('marketplace_variant_imports')->where('listing_variant_id', $listingId)->lockForUpdate()->first();
            if ($identity) {
                $variant = ProductVariant::findOrFail($identity->product_variant_id);
                abort_unless((int) $variant->product_id === $destinationProductId, 409,
                    'SKU '.$variant->sku.' already belongs to '.$variant->product->title.' (Product #'.$variant->product_id.'). Selected destination: Product #'.$destinationProductId.'. Choose its existing product or review a separate ownership transfer; no duplicate was created.');
                return (int) $variant->id;
            }
            abort_if(DB::table('marketplace_variant_mappings')->where('listing_variant_id', $listingId)->exists(),
                409, 'This external variant is already linked.');
            $parentImport = DB::table('marketplace_product_imports')->where('store_id', $source->store_id)
                ->where('external_product_id', $source->external_product_id)->first();
            abort_if($parentImport && (int) $parentImport->product_id !== $destinationProductId,
                409, 'The imported parent belongs to another JW product.');
            $product = Product::whereKey($destinationProductId)->lockForUpdate()->firstOrFail();
            $data = json_decode($source->observed_data, true, 512, JSON_THROW_ON_ERROR);
            $data=app(MarketplaceMediaIngest::class)->normalize($data);
            $sku = $reviewedSku ?? ($data['sku'] ?? null);
            $title = $data['title'] ?? null;
            abort_unless(is_string($sku) && trim($sku) !== '' && mb_strlen($sku) <= 255
                && is_string($title) && trim($title) !== '' && mb_strlen($title) <= 255,
                422, 'Review the variant title and SKU before import.');
            $sku = trim($sku);
            abort_unless(($data['currency'] ?? null) === $this->currencies->default()['code'],
                422, 'Channel currency differs from JW currency.');
            $price = $data['price'] ?? null;
            abort_unless(is_scalar($price) && preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $price),
                422, 'Review the channel price before import.');
            [$major, $minor] = array_pad(explode('.', (string) $price, 2), 2, '');
            $cents = (int) $major * 100 + (int) str_pad($minor, 2, '0');
            abort_if($cents > 4294967295, 422, 'Channel price exceeds the supported range.');
            $existing=ProductVariant::where('sku',$sku)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int)$existing->product_id===$destinationProductId,409,
                    'SKU '.$sku.' already belongs to '.$existing->product->title.' (Product #'.$existing->product_id.'). Selected destination: Product #'.$destinationProductId.'. No duplicate was created.');
                $this->mappings->map($listingId,$existing->id,$destinationProductId,$actorId);
                return (int)$existing->id;
            }
            $variant = $product->variants()->create(['sku'=>$sku, 'title'=>trim($title), 'price'=>$cents, 'stock'=>0, 'enabled'=>true] + $this->variantDetails($data));
            ImportedProductOptions::merge($product,$variant->options??[]);
            app(\App\Services\Inventory\TestDataClassificationService::class)->inheritVariant((int)$variant->id,(int)$source->store_id);
            $this->mappings->map($listingId, $variant->id, $product->id, $actorId);
            DB::table('marketplace_variant_imports')->insert(['listing_variant_id'=>$listingId,
                'product_variant_id'=>$variant->id, 'actor_id'=>$actorId, 'created_at'=>now(), 'updated_at'=>now()]);
            if ($product->variants()->count() > 1 && !$product->has_variants) {
                $product->update(['has_variants'=>true]);
            }
            app(\App\Domain\Catalog\CatalogMediaReferenceSynchronizer::class)->syncProduct($product);
            return (int) $variant->id;
        });
    }

    public function __construct(private MarketplaceFoundationService $mappings, private CurrencySettings $currencies,
        private MarketplaceProductDescription $descriptions) {}

    public function importProduct(int $storeId, string $externalProductId, int $actorId, array $skuOverrides = []): int
    {
        return DB::transaction(function () use ($storeId, $externalProductId, $actorId, $skuOverrides) {
            abort_unless(DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first(), 404);
            $import = DB::table('marketplace_product_imports')->where('store_id', $storeId)
                ->where('external_product_id', $externalProductId)->first();
            // Import identity survives unlinking. Do not silently restore an employee's removed mapping.
            if ($import) return (int) $import->product_id;
            $listings = DB::table('marketplace_listing_variants')->where('store_id', $storeId)
                ->where('external_product_id', $externalProductId)->orderBy('id')->lockForUpdate()->get();
            abort_if($listings->isEmpty(), 404, 'Fetch this product before importing it.');
            abort_if(array_diff(array_map('strval',array_keys($skuOverrides)), $listings->pluck('id')->map(fn($id)=>(string)$id)->all()),
                422, 'SKU overrides must belong to this product.');
            abort_if(DB::table('marketplace_variant_imports')->whereIn('listing_variant_id', $listings->pluck('id'))->exists(),
                409, 'A variant was already imported. Review its existing JW product and import only missing variants.');
            abort_if(DB::table('marketplace_variant_mappings')->whereIn('listing_variant_id', $listings->pluck('id'))->exists(),
                409, 'This product already has mappings. Review its existing JW product before importing.');
            $currency = $this->currencies->default()['code'];
            $prepared = [];
            $skus = [];
            $images = [];
            foreach ($listings as $listing) {
                $data = json_decode($listing->observed_data, true, 512, JSON_THROW_ON_ERROR);
                $data=app(MarketplaceMediaIngest::class)->normalize($data);
                $description = $this->descriptions->render($data);
                $brand = $data['brand'] ?? null;
                abort_unless($brand === null || (is_string($brand) && mb_strlen($brand) <= 255),
                    422, 'Review the channel brand before import.');
                $brand = $brand === null ? null : (trim($brand) ?: null);
                $gallery = $data['productImages'] ?? [];
                abort_unless(is_array($gallery) && array_is_list($gallery), 422, 'Review the channel image list before import.');
                if (!$gallery && isset($data['productImage']) && $data['productImage'] !== '') {
                    $gallery = [$data['productImage']];
                }
                foreach ($gallery as $image) {
                    abort_unless(is_string($image) && $image !== '' && strlen($image) <= 255 && MediaUrlPolicy::isSafe($image),
                        422, 'Review the channel image URL before import.');
                    if (!in_array($image, $images, true)) $images[] = $image;
                }
                $rawSku = $skuOverrides[$listing->id] ?? ($data['sku'] ?? '');
                abort_unless(is_string($rawSku), 422, 'Review the SKU code before import.');
                $sku = trim($rawSku);
                $title = trim((string) ($data['title'] ?? ''));
                $productTitle = trim((string) ($data['productTitle'] ?? ''));
                abort_if($sku === '' || mb_strlen($sku) > 255 || $title === '' || mb_strlen($title) > 255
                    || $productTitle === '' || mb_strlen($productTitle) > 255, 422, 'Product titles and SKU codes must be complete before import.');
                abort_unless(($data['currency'] ?? null) === $currency, 422, 'Channel currency differs from JW currency. Review pricing before import.');
                $price = (string) ($data['price'] ?? '');
                abort_unless(preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $price), 422, 'Review the channel price before import.');
                [$major, $minor] = array_pad(explode('.', $price, 2), 2, '');
                $cents = (int) $major * 100 + (int) str_pad($minor, 2, '0');
                abort_if($cents > 4294967295, 422, 'Channel price exceeds the supported JW price range.');
                abort_if(in_array(mb_strtolower($sku), $skus, true) || ProductVariant::where('sku', $sku)->exists(),
                    409, 'A SKU already exists. Link the existing JW variant instead of importing a duplicate.');
                $skus[] = mb_strtolower($sku);
                $details=$this->variantDetails($data);
                $prepared[] = compact('listing', 'sku', 'title', 'productTitle', 'cents', 'description', 'brand', 'details');
            }
            $product = Product::create(['title'=>$prepared[0]['productTitle'],
                'handle'=>'marketplace-'.Str::uuid(), 'base_price'=>min(array_column($prepared, 'cents')),
                'status'=>'draft', 'has_variants'=>count($prepared) > 1, 'is_featured'=>false,
                'description'=>$prepared[0]['description'], 'brand'=>$prepared[0]['brand']]);
            foreach ($images as $position => $image) {
                $product->images()->create(['path'=>$image, 'alt'=>$prepared[0]['productTitle'], 'position'=>$position]);
            }
            foreach ($prepared as $row) {
                $variant = $product->variants()->create(['sku'=>$row['sku'], 'title'=>$row['title'],
                    'price'=>$row['cents'], 'stock'=>0, 'enabled'=>true] + $row['details']);
                ImportedProductOptions::merge($product,$variant->options??[]);
            app(\App\Services\Inventory\TestDataClassificationService::class)->inheritVariant((int)$variant->id,$storeId);
                $this->mappings->map($row['listing']->id, $variant->id, $product->id, $actorId);
            }
            DB::table('marketplace_product_imports')->insert(['store_id'=>$storeId, 'external_product_id'=>$externalProductId,
                'product_id'=>$product->id, 'actor_id'=>$actorId, 'created_at'=>now(), 'updated_at'=>now()]);

            app(\App\Domain\Catalog\CatalogMediaReferenceSynchronizer::class)->syncProduct($product);
            return $product->id;
        });
    }
}
