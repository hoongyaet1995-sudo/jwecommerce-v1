<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use App\Models\ProductHandleRedirect;
use Illuminate\Support\Str;

class ProductHandleAllocator
{
    public function allocate(string $title): string
    {
        $base = Str::of($title)
            ->slug()
            ->substr(0, 240)
            ->trim('-')
            ->value() ?: 'product';

        $reserved = Product::query()
            ->where(fn ($query) => $query->where('handle', $base)->orWhere('handle', 'like', $base.'-%'))
            ->lockForUpdate()
            ->pluck('handle')
            ->all();

        $redirects = ProductHandleRedirect::query()
            ->where(fn ($query) => $query->where('from_handle', $base)->orWhere('from_handle', 'like', $base.'-%'))
            ->lockForUpdate()
            ->pluck('from_handle')
            ->all();

        $used = array_fill_keys([...$reserved, ...$redirects], true);
        if (! isset($used[$base])) {
            return $base;
        }

        for ($suffix = 2; ; $suffix++) {
            $candidate = Str::of($base)->substr(0, 255 - strlen((string) $suffix) - 1)->rtrim('-')->append('-'.$suffix)->value();
            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }
    }
}
