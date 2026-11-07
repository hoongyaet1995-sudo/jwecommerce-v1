<?php

namespace App\Services;

use App\Models\Collection as ProductCollection;
use App\Models\Product;

class DynamicCollectionService
{
    public function syncAll(): void
    {
        ProductCollection::query()->where('type', 'dynamic')->each(fn (ProductCollection $collection) => $this->sync($collection));
    }

    public function sync(ProductCollection $collection): void
    {
        if ($collection->type !== 'dynamic') {
            return;
        }

        $rules = collect($collection->dynamic_rules ?? []);
        if ($rules->isEmpty()) {
            $collection->products()->sync([]);
            return;
        }

        $productIds = Product::query()
            ->where('status', 'active')
            ->with(['category:id,slug,name', 'variants:id,product_id,stock'])
            ->get(['id', 'category_id', 'base_price', 'brand', 'tags'])
            ->filter(function (Product $product) use ($rules, $collection): bool {
                $matches = $rules->map(fn (array $rule): bool => $this->matches($product, $rule));
                return $collection->match_type === 'any' ? $matches->contains(true) : $matches->every(fn (bool $match) => $match);
            })
            ->pluck('id');

        $collection->products()->sync($productIds);
    }

    /** @param array{field?: string, operator?: string, value?: string} $rule */
    public function matches(Product $product, array $rule): bool
    {
        $actual = match ($rule['field'] ?? '') {
            'price' => $product->base_price / 100,
            'category' => $product->category?->slug ?? (string) $product->category_id,
            'tag' => $product->tags ?? [],
            'vendor' => $product->brand ?? '',
            'inventory' => $product->variants->sum('stock'),
            default => null,
        };
        $expected = $rule['value'] ?? '';
        $operator = $rule['operator'] ?? 'equals';

        if (is_array($actual)) {
            $contains = collect($actual)->contains(fn ($value) => mb_strtolower((string) $value) === mb_strtolower($expected));
            return $operator === 'not_equals' ? ! $contains : $contains;
        }

        if (in_array($rule['field'] ?? '', ['price', 'inventory'], true)) {
            return match ($operator) {
                'greater_than' => (float) $actual > (float) $expected,
                'less_than' => (float) $actual < (float) $expected,
                'not_equals' => (float) $actual !== (float) $expected,
                default => (float) $actual === (float) $expected,
            };
        }

        $actualText = mb_strtolower((string) $actual);
        $expectedText = mb_strtolower($expected);
        return match ($operator) {
            'not_equals' => $actualText !== $expectedText,
            'contains' => str_contains($actualText, $expectedText),
            default => $actualText === $expectedText,
        };
    }
}
