<?php

namespace App\Services\Search;

use App\Models\Product;

class SearchStructuredData
{
    public function graph(array $head, ?string $kind = null, ?int $id = null): ?array
    {
        $origin = app(SearchSettings::class)->origin();
        if ($head['noindex'] || ! $head['canonical'] || ! $origin) {
            return null;
        }
        $identity = app(BusinessIdentity::class)->read();
        $organization = ['@type' => 'Organization', '@id' => $origin.'/#organization', 'name' => $identity['name'], 'url' => $origin.'/'];
        if ($logo = $this->url($identity['logo'], $origin)) {
            $organization['logo'] = $logo;
        }
        $page = ['@type' => in_array($kind, ['category', 'collection']) ? 'CollectionPage' : 'WebPage', '@id' => $head['canonical'].'#webpage', 'url' => $head['canonical'], 'name' => $head['title'], 'isPartOf' => ['@id' => $origin.'/#website']];
        if ($head['description']) {
            $page['description'] = $head['description'];
        }
        $graph = [$organization, ['@type' => 'WebSite', '@id' => $origin.'/#website', 'url' => $origin.'/', 'name' => $identity['name'], 'publisher' => ['@id' => $organization['@id']]], $page];
        if ($kind && $id) {
            $metadata = app(SearchMetadata::class);
            // Independently enforce public eligibility even when called outside SearchHead.
            if (! $metadata->public($kind, $id)) {
                return null;
            }
            $facts = $metadata->facts($kind, $id, $kind === 'page' ? 'published' : 'live');
            if ($head['canonical'] !== $origin.'/') {
                $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => $identity['name'], 'item' => $origin.'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $facts['name'], 'item' => $head['canonical']],
                ]];
            }
            if ($kind === 'product') {
                $product = Product::findOrFail($id);
                $node = ['@type' => 'Product', '@id' => $head['canonical'].'#product', 'name' => $product->title, 'url' => $head['canonical'], 'mainEntityOfPage' => ['@id' => $page['@id']]];
                if ($facts['description']) {
                    $node['description'] = $facts['description'];
                }
                $images = $product->images()->orderBy('position')->pluck('path')->map(fn ($path) => $this->url($path, $origin))->filter()->values()->all();
                if ($images) {
                    $node['image'] = $images;
                }
                if (trim((string) $product->brand) !== '') {
                    $node['brand'] = ['@type' => 'Brand', 'name' => $product->brand];
                }
                // Offers/ratings require a separate price, variant, stock and moderation contract.
                // In particular, quote/display products must never acquire synthetic zero-price offers.
                $graph[] = $node;
            }
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    private function url(?string $value, string $origin): ?string
    {
        $value = trim($value ?? '');
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $origin.$value;
        }
        $parts = parse_url($value);

        return $parts && in_array($parts['scheme'] ?? '', ['https', 'http'], true) && ! isset($parts['user']) && ! isset($parts['pass']) && isset($parts['host']) ? $value : null;
    }
}
