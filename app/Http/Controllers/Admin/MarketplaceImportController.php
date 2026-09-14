<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplaceImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceImportController extends Controller
{
    public function variant(Request $request, int $listing, MarketplaceImportService $imports)
    {
        if($request->has('reviewTransfer') || $request->has('transferToken')) {
            abort_if(array_diff(array_keys($request->except('_token')),['productId','reviewTransfer','transferToken','reason']),422);
            $data=$request->validate(['productId'=>['required','integer','min:1'],'reviewTransfer'=>['sometimes','accepted'],'transferToken'=>['sometimes','required','string'],'reason'=>['sometimes','string','max:500']]);
            abort_if($request->has('reviewTransfer') && $request->has('transferToken'),422);
            return response()->json(app(\App\Services\Marketplace\VariantOwnershipTransfer::class)->run($listing,(int)$data['productId'],$request->user()->id,$data['transferToken']??null,$data['reason']??null));
        }
        abort_if(array_diff(array_keys($request->except('_token')), ['productId', 'newProduct', 'sku']), 422,
            'Only the destination JW product and reviewed SKU may be selected.');
        $review = $request->validate(['sku'=>['sometimes', 'required', 'string', 'max:255']]);
        if ($request->exists('newProduct')) {
            abort_unless($request->input('newProduct') === true && !$request->exists('productId'),
                422, 'Choose either a new draft or an existing JW product.');
            $id = $imports->importVariantAsProduct($listing, $request->user()->id, $review['sku'] ?? null);
        } else {
            $data = $request->validate(['productId'=>['required', 'integer', 'min:1', 'exists:products,id']]);
            $id = $imports->importVariant($listing, (int) $data['productId'], $request->user()->id, $review['sku'] ?? null);
        }

        return response()->json(['variantId'=>$id, 'productId'=>(int) DB::table('product_variants')->where('id', $id)->value('product_id')])
            ->header('Cache-Control', 'no-store');
    }

    public function product(Request $request, int $listing, MarketplaceImportService $imports,
        \App\Services\Marketplace\MarketplaceReviewedImportService $reviewedImports)
    {
        abort_if(array_diff(array_keys($request->except('_token')), ['skuOverrides', 'existingLinks']), 422, 'Only reviewed SKU overrides and links are accepted.');
        $review = $request->validate(['skuOverrides'=>['sometimes','array','max:1000'],
            'skuOverrides.*'=>['required','string','max:255'],
            'existingLinks'=>['sometimes','array','max:1000'],
            'existingLinks.*'=>['required','array:productId,variantId'],
            'existingLinks.*.productId'=>['required','integer','min:1'],
            'existingLinks.*.variantId'=>['required','integer','min:1']]);
        $source = DB::table('marketplace_listing_variants')->find($listing);
        abort_unless($source, 404);
        if ($request->exists('existingLinks')) {
            $links = array_map(fn ($link) => ['productId'=>(int) $link['productId'], 'variantId'=>(int) $link['variantId']], $review['existingLinks']);
            $result = $reviewedImports->import($source->store_id, $source->external_product_id,
                $request->user()->id, $review['skuOverrides'] ?? [], $links);
            $result['status'] = $result['productId'] === null ? null
                : DB::table('products')->where('id', $result['productId'])->value('status');
            return response()->json($result)->header('Cache-Control', 'no-store');
        }
        $id = $imports->importProduct($source->store_id, $source->external_product_id, $request->user()->id, $review['skuOverrides'] ?? []);

        // A retry may refer to a draft that the merchant subsequently edited or published.
        return response()->json(['productId'=>$id, 'status'=>DB::table('products')->where('id', $id)->value('status')])
            ->header('Cache-Control', 'no-store');
    }
}
