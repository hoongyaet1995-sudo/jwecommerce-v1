<?php

namespace App\Domain\Cms;

use App\Models\Product;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Support\Collection;

final class ProductListingPresentation
{
    /** Format through the same currency owner as PLP; only public pages mount purchase UI. */
    public function prepare(array &$payload): Collection
    {
        $ids = [];
        foreach ($payload['runtime']['sections'] as &$facts) {
            if (! isset($facts['listing']['products'])) {
                continue;
            }
            foreach ($facts['listing']['products'] as &$product) {
                $commerce = &$product['commerce'];
                $money = app(StorefrontCurrencyContext::class);
                $commerce['priceLabel'] = $money->format($commerce['price'] * 100);
                $commerce['compareAtLabel'] = $commerce['compareAtPrice'] === null ? '' : $money->format($commerce['compareAtPrice'] * 100);
                foreach ($commerce['variants'] ?? [] as $index => $variant) {
                    $commerce['variants'][$index]['priceLabel'] = $money->format($variant['price'] * 100);
                    $commerce['variants'][$index]['compareAtLabel'] = $variant['compareAtPrice'] === null ? '' : $money->format($variant['compareAtPrice'] * 100);
                }
                $ids[] = $product['id'];
            }
            unset($product, $commerce);
        }
        unset($facts);
        if (($payload['context']['mode'] ?? null) !== 'public' || $ids === []) {
            return collect();
        }

        return Product::query()->whereKey(array_unique($ids))->where('status', 'active')->whereHas('variants')
            ->with(['variants' => fn ($query) => $query->orderBy('id'), 'images' => fn ($query) => $query->orderBy('position')->orderBy('id')])->get();
    }
}
