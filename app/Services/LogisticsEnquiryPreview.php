<?php
namespace App\Services;
use App\Models\Product;

/** Presentation-only exception limited to authenticated Admin-owned fixture identity. */
final class LogisticsEnquiryPreview
{
    public function allows(Product $product): bool
    {
        $request=request();
        return $request->routeIs('admin.website-design.preview','admin.website-design.acceptance.sample')
            && (bool) $request->user('web')?->is_admin
            && $request->attributes->get('websiteDesignPreview')==='logistics'
            && ! $product->exists
            && collect($request->attributes->get('websiteThemeDemoProducts',[]))->contains(fn ($fixture) => $fixture === $product);
    }
}
