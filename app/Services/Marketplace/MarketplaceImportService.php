<?php

namespace App\Services\Marketplace;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceImportService
{
    public function __construct(private MarketplaceFoundationService $mappings, private CurrencySettings $currencies) {}

    public function importProduct(int $storeId, string $externalProductId, int $actorId): int
    {
        return DB::transaction(function () use ($storeId, $externalProductId, $actorId) {
            abort_unless(DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first(), 404);
            $import = DB::table('marketplace_product_imports')->where('store_id', $storeId)
                ->where('external_product_id', $externalProductId)->first();
            // Import identity survives unlinking. Do not silently restore an employee's removed mapping.
            if ($import) return (int) $import->product_id;
            $listings = DB::table('marketplace_listing_variants')->where('store_id', $storeId)
                ->where('external_product_id', $externalProductId)->orderBy('id')->lockForUpdate()->get();
            abort_if($listings->isEmpty(), 404, 'Fetch this product before importing it.');
            abort_if(DB::table('marketplace_variant_mappings')->whereIn('listing_variant_id', $listings->pluck('id'))->exists(),
                409, 'This product already has mappings. Review its existing JW product before importing.');
            $currency = $this->currencies->default()['code'];
            $prepared = [];
            $skus = [];
            foreach ($listings as $listing) {
                $data = json_decode($listing->observed_data, true, 512, JSON_THROW_ON_ERROR);
                $sku = trim((string) ($data['sku'] ?? ''));
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
                $prepared[] = compact('listing', 'sku', 'title', 'productTitle', 'cents');
            }
            $product = Product::create(['title'=>$prepared[0]['productTitle'],
                'handle'=>'marketplace-'.Str::uuid(), 'base_price'=>min(array_column($prepared, 'cents')),
                'status'=>'draft', 'has_variants'=>count($prepared) > 1, 'is_featured'=>false]);
            foreach ($prepared as $row) {
                $variant = $product->variants()->create(['sku'=>$row['sku'], 'title'=>$row['title'],
                    'price'=>$row['cents'], 'stock'=>0, 'enabled'=>true]);
                $this->mappings->map($row['listing']->id, $variant->id, $product->id, $actorId);
            }
            DB::table('marketplace_product_imports')->insert(['store_id'=>$storeId, 'external_product_id'=>$externalProductId,
                'product_id'=>$product->id, 'actor_id'=>$actorId, 'created_at'=>now(), 'updated_at'=>now()]);

            return $product->id;
        });
    }
}
