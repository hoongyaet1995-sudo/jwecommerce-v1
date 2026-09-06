<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplaceImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceImportController extends Controller
{
    public function product(Request $request, int $listing, MarketplaceImportService $imports)
    {
        abort_if(count($request->except('_token')) > 0, 422, 'Import uses the saved channel catalog. Additional fields are not accepted.');
        $source = DB::table('marketplace_listing_variants')->find($listing);
        abort_unless($source, 404);
        $id = $imports->importProduct($source->store_id, $source->external_product_id, $request->user()->id);

        // A retry may refer to a draft that the merchant subsequently edited or published.
        return response()->json(['productId'=>$id, 'status'=>DB::table('products')->where('id', $id)->value('status')])
            ->header('Cache-Control', 'no-store');
    }
}
