<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Services\CatalogListingSettingsService;
use App\Support\ShopBrand;

class SearchHead
{
    public function resolve(?string $title, ?string $description): array
    {
        $r = request();
        $metadata = app(SearchMetadata::class);
        $settings = app(SearchSettings::class);
        $kind = null;
        $id = null;
        $stage = 'live';
        if ($r->routeIs('storefront.products.show')) {
            $kind = 'product';
            $id = Product::where('handle', $r->route('handle'))->value('id');
        } elseif ($r->routeIs('storefront.categories.show')) {
            $kind = 'category';
            $value = $r->route('category');
            $id = $value instanceof Category ? $value->id : null;
        } elseif ($r->routeIs('storefront.collections.show')) {
            $kind = 'collection';
            $value = $r->route('collection');
            $id = $value instanceof Collection ? $value->id : null;
        } elseif ($r->attributes->get('searchPageId')) {
            $kind = 'page';
            $id = $r->attributes->get('searchPageId');
            $stage = 'published';
        }
        $data = $kind && $id ? $metadata->for($kind, (int) $id, $stage) : ['title' => $title ?: app(ShopBrand::class)->name(), 'description' => $description, 'excluded' => false];
        $path = $kind && $id ? $metadata->path($kind, (int) $id) : $r->getPathInfo();
        if ($r->routeIs('storefront.index')) {
            $listing = app(CatalogListingSettingsService::class)->live();
            $data['description'] = $description ?: ($listing['description'] ?? null);
        }
        $public = $r->routeIs('storefront.*') && ! $r->is('page-template-sample/*');
        $filtered = count(array_intersect(array_keys($r->query()), ['q', 'search', 'tag', 'tags', 'min_price', 'max_price', 'category', 'color', 'material', 'in_stock', 'rating'])) > 0;
        $noindex = ! $public || $settings->read()['preview'] || ($data['excluded'] ?? false) || $filtered;
        $canonical = $public ? $settings->origin().$path : null;
        if ($canonical && $r->routeIs('storefront.index', 'storefront.categories.show', 'storefront.collections.show') && $r->integer('page') > 1) {
            $canonical .= '?page='.$r->integer('page');
        }
        $r->attributes->set('searchNoindex', $noindex);

        $head = ['title' => $data['title'], 'description' => $data['description'] ?? null, 'canonical' => $canonical, 'noindex' => $noindex];
        $head['structuredData'] = app(SearchStructuredData::class)->graph($head, $kind, $id ? (int) $id : null);
        return $head;
    }
}
