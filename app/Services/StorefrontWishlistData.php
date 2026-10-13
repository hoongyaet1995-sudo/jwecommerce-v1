<?php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/** Reuses the normal Quick Buy projection for products already present in the customer's wishlist. */
final class StorefrontWishlistData
{
    public function products(array $wishlist): Collection
    {
        $ids=collect($wishlist)->pluck('product.id')->all();
        $demo=request()->attributes->get('websiteThemeDemoProducts');
        if($demo instanceof Collection)return $demo->whereIn('id',$ids)->keyBy('id');
        return Product::whereIn('id',$ids)->where('status','active')->with(['variants','images'])->get()->keyBy('id');
    }
}
